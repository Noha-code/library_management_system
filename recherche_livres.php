<?php
// Inclusion du fichier de connexion
require_once 'db_connect.php';
// Démarrer la session (à mettre au tout début de votre script)
session_start();

// Fonction pour vérifier si l'utilisateur est connecté
function isUserLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Fonction pour enregistrer une recherche dans l'historique
function saveSearchHistory($pdo, $user_id, $search_query, $category_id, $genre_id, $year) {
    try {
        $sql = "INSERT INTO search_history (user_id, search_query, category_id, genre_id, year) 
                VALUES (:user_id, :search_query, :category_id, :genre_id, :year)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':search_query', $search_query);
        
        // Gérer les valeurs NULL pour les filtres
        if (empty($category_id)) {
            $stmt->bindValue(':category_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
        }
        
        if (empty($genre_id)) {
            $stmt->bindValue(':genre_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':genre_id', $genre_id, PDO::PARAM_INT);
        }
        
        if (empty($year)) {
            $stmt->bindValue(':year', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':year', $year);
        }
        
        $stmt->execute();
        return true;
    } catch (PDOException $e) {
        // En cas d'erreur, on peut journaliser l'erreur mais on continue le script
        error_log("Erreur d'enregistrement de l'historique : " . $e->getMessage());
        return false;
    }
}

// Fonction pour récupérer l'historique des recherches d'un utilisateur
function getUserSearchHistory($pdo, $user_id, $limit = 10) {
    try {
        $sql = "SELECT sh.*, c.nom_categorie, g.nom_genre 
                FROM search_history sh
                LEFT JOIN categorie c ON sh.category_id = c.id_categorie
                LEFT JOIN genre g ON sh.genre_id = g.id_genre
                WHERE sh.user_id = :user_id 
                ORDER BY sh.search_date DESC 
                LIMIT :limit";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur de récupération de l'historique : " . $e->getMessage());
        return [];
    }
}

// Fonction pour appeler l'API NLP et obtenir des mots-clés supplémentaires
function getNlpKeywords($text) {
    if (empty($text)) {
        return [];
    }
    
    $apiUrl = "http://localhost:5001/analyser";
    $data = ['texte' => $text];
    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data)
        ]
    ];
    
    $context = stream_context_create($options);
    
    try {
        $result = file_get_contents($apiUrl, false, $context);
        if ($result === FALSE) {
            error_log("Erreur lors de l'appel à l'API NLP");
            return [];
        }
        
        $response = json_decode($result, true);
        return $response['mots_cles'] ?? [];
    } catch (Exception $e) {
        error_log("Exception lors de l'appel à l'API NLP: " . $e->getMessage());
        return [];
    }
}

// Maintenant, ajoutez ce code dans la section où vous traitez la recherche,
// juste après avoir vérifié les résultats de la recherche

// Enregistrer la recherche dans l'historique si l'utilisateur est connecté
if ($_SERVER['REQUEST_METHOD'] === 'POST' || !empty($_GET['recherche']) || isset($_GET['categorie']) || isset($_GET['genre']) || isset($_GET['annee'])) {
    if (isUserLoggedIn()) {
        $user_id = $_SESSION['user_id'];
        saveSearchHistory($pdo, $user_id, $recherche, $categorie, $genre, $annee);
    }
}

// Initialisation des variables
$recherche = '';
$categorie = '';
$genre = '';
$annee = '';
$resultats = [];
$message = '';
$limite = 50; // Limite par défaut du nombre de résultats
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1; // Assure que la page est au moins 1
$offset = ($page - 1) * $limite;
$total_resultats = 0;
$nlp_active = isset($_POST['nlp_active']) ? $_POST['nlp_active'] : (isset($_GET['nlp_active']) ? $_GET['nlp_active'] : '0');

// Traitement de la recherche si le formulaire est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST' || !empty($_GET['recherche']) || isset($_GET['categorie']) || isset($_GET['genre']) || isset($_GET['annee'])) {
    // Récupération des données du formulaire
    $recherche = isset($_POST['recherche']) ? trim($_POST['recherche']) : (isset($_GET['recherche']) ? trim($_GET['recherche']) : '');
    $categorie = isset($_POST['categorie']) ? $_POST['categorie'] : (isset($_GET['categorie']) ? $_GET['categorie'] : '');
    $genre = isset($_POST['genre']) ? $_POST['genre'] : (isset($_GET['genre']) ? $_GET['genre'] : '');
    $annee = isset($_POST['annee']) ? trim($_POST['annee']) : (isset($_GET['annee']) ? trim($_GET['annee']) : '');
    
    // Construction de la requête SQL de base
    $sql = "SELECT l.*, c.nom_categorie, g.nom_genre 
            FROM livres l
            LEFT JOIN categorie c ON l.id_categorie = c.id_categorie
            LEFT JOIN genre g ON l.id_genre = g.id_genre
            WHERE 1=1";

    $sql_count = "SELECT COUNT(*) FROM livres l
                 LEFT JOIN categorie c ON l.id_categorie = c.id_categorie
                 LEFT JOIN genre g ON l.id_genre = g.id_genre
                 WHERE 1=1";

    $params = [];
    $params_count = [];

    // Si la recherche NLP est activée, récupérer les mots-clés
    $mots_cles = [];
    if ($nlp_active == '1' && !empty($recherche)) {
        $mots_cles = getNlpKeywords($recherche);
    }

    // Ajout des conditions de recherche avec des vérifications améliorées
    if (!empty($recherche)) {
        if (!empty($mots_cles) && $nlp_active == '1') {
            // Recherche avancée avec NLP
            $search_terms = array_merge([$recherche], $mots_cles);
            $search_conditions = [];
            $search_params = [];
            
            foreach ($search_terms as $index => $term) {
                $param_titre = ":titre_$index";
                $param_auteur = ":auteur_$index";
                $search_conditions[] = "(l.titre LIKE $param_titre OR l.auteur LIKE $param_auteur)";
                $search_params[$param_titre] = "%{$term}%";
                $search_params[$param_auteur] = "%{$term}%";
            }
            
            $sql .= " AND (" . implode(" OR ", $search_conditions) . ")";
            $params = array_merge($params, $search_params);
            
            $sql_count .= " AND (" . implode(" OR ", $search_conditions) . ")";
            $params_count = array_merge($params_count, $search_params);
        } else {
            // Recherche standard sans NLP
            $sql .= " AND (l.titre LIKE :titre_exact OR l.auteur LIKE :auteur)";
            $params[':titre_exact'] = "%{$recherche}%";
            $params[':auteur'] = "%{$recherche}%";
            $sql_count .= " AND (l.titre LIKE :titre_exact OR l.auteur LIKE :auteur)";
            $params_count[':titre_exact'] = "%{$recherche}%";
            $params_count[':auteur'] = "%{$recherche}%";
        }
    }

    // Vérifications pour les filtres
    if (!empty($categorie)) {
        $sql .= " AND l.id_categorie = :categorie";
        $params[':categorie'] = $categorie;
        $sql_count .= " AND l.id_categorie = :categorie";
        $params_count[':categorie'] = $categorie;
    }

    if (!empty($genre)) {
        $sql .= " AND l.id_genre = :genre";
        $params[':genre'] = $genre;
        $sql_count .= " AND l.id_genre = :genre";
        $params_count[':genre'] = $genre;
    }
    
    if (!empty($annee)) {
        $sql .= " AND l.annee_parution = :annee";
        $params[':annee'] = $annee;
        $sql_count .= " AND l.annee_parution = :annee";
        $params_count[':annee'] = $annee;
    }
    
    // Ajout des clauses ORDER BY, LIMIT et OFFSET
    $sql .= " ORDER BY l.titre ASC LIMIT :limite OFFSET :offset";
    $params[':limite'] = $limite;
    $params[':offset'] = $offset;
    
    // Exécution de la requête
    try {
        // Compter le nombre total de résultats
        $stmt_count = $pdo->prepare($sql_count);
        foreach ($params_count as $key => $value) {
            $stmt_count->bindValue($key, $value);
        }
        $stmt_count->execute();
        $total_resultats = $stmt_count->fetchColumn();
        
        // Exécuter la requête principale
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            if ($key === ':limite') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else if ($key === ':offset') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        $stmt->execute();
        $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($resultats) === 0) {
            $message = "Aucun livre ne correspond à votre recherche.";
        }
    } catch (PDOException $e) {
        $message = "Erreur lors de la recherche : " . $e->getMessage();
    }
}

// Récupération des catégories pour le formulaire
$categories = [];
try {
    $stmt = $pdo->query("SELECT * FROM categorie ORDER BY nom_categorie ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message .= ($message ? "<br>" : "") . "Erreur lors de la récupération des catégories : " . $e->getMessage();
}

// Récupération des genres pour le formulaire
$genres = [];
try {
    $stmt = $pdo->query("SELECT * FROM genre ORDER BY nom_genre ASC");
    $genres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message .= ($message ? "<br>" : "") . "Erreur lors de la récupération des genres : " . $e->getMessage();
}

// Récupération des années disponibles pour le formulaire
$annees = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT annee_parution FROM livres WHERE annee_parution IS NOT NULL AND annee_parution != '' ORDER BY annee_parution DESC");
    $annees = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $message .= ($message ? "<br>" : "") . "Erreur lors de la récupération des années : " . $e->getMessage();
}

// Calcul de la pagination
$total_pages = $total_resultats > 0 ? ceil($total_resultats / $limite) : 1;

// S'assurer que la page actuelle ne dépasse pas le nombre total de pages
if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $limite;
    // Rediriger vers la bonne page si nécessaire
    if (!empty($_SERVER['QUERY_STRING'])) {
        $query_params = $_GET;
        $query_params['page'] = $page;
        $query_string = http_build_query($query_params);
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . $query_string);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recherche de livres - Bibliothèque</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&display=swap">
    <style>
        body {
            margin: 0;
            font-family: 'Libre Baskerville', Georgia, 'Times New Roman', serif;
            background-image: url('https://images.unsplash.com/photo-1507842217343-583bb7270b66?ixlib=rb-1.2.1&auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-attachment: fixed;
            background-position: center;
            line-height: 1.6;
            color: #333;
        }
        
        nav {
            background-color: #512b58;
            padding: 10px 20px;
            color: white;
        }

        nav ul {
            list-style: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 0;
            padding: 0;
        }

        nav ul li {
            margin: 0 15px;
        }

        nav ul li a {
            color: white;
            text-decoration: none;
            font-weight: bold;
        }

        .login-btn a {
            background-color: #f4b083;
            color: black;
            padding: 8px 14px;
            border-radius: 8px;
            text-decoration: none;
        }
        
        .container {
            max-width: 1200px;
            margin: 20px auto;
            background: rgba(255, 255, 255, 0.9);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        
        h1 {
            color: #512b58;
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #f4b083;
            padding-bottom: 10px;
        }
        
        h2 {
            color: #512b58;
            margin-top: 30px;
        }
        
        form {
            margin-bottom: 30px;
            background-color: rgba(255, 255, 255, 0.7);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }
        
        .form-group {
            margin-bottom: 15px;
            padding: 0 10px;
            flex: 1;
            min-width: 200px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #512b58;
        }
        
        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            transition: border-color 0.3s;
            font-family: 'Libre Baskerville', Georgia, 'Times New Roman', serif;
        }
        
        input:focus, select:focus {
            border-color: #f4b083;
            outline: none;
        }
        
        button {
            background-color: #512b58;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
            width: 100%;
            margin-top: 10px;
            font-family: 'Libre Baskerville', Georgia, 'Times New Roman', serif;
        }
        
        button:hover {
            background-color: #43224a;
        }
        
        .message {
            margin: 20px 0;
            padding: 10px;
            background-color: #f8d7da;
            color: #721c24;
            border-radius: 5px;
            text-align: center;
        }
        
        .resultats {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .livre {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            background-color: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .livre:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .livre-image {
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f9f9f9;
            margin-bottom: 15px;
            overflow: hidden;
            border-radius: 4px;
        }
        
        .livre-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .no-image {
            color: #aaa;
            font-style: italic;
            text-align: center;
        }
        
        .livre-content {
            flex-grow: 1;
        }
        
        .livre h3 {
            margin-top: 0;
            color: #512b58;
            font-size: 18px;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
        }
        
        .livre p {
            margin: 8px 0;
            color: #444;
        }
        
        .info {
            font-size: 12px;
            color: #999;
            margin-top: auto;
            padding-top: 10px;
            border-top: 1px dotted #eee;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin: 30px 0;
            list-style: none;
            padding: 0;
        }
        
        .pagination li {
            margin: 0 5px;
        }
        
        .pagination a, .pagination span {
            display: block;
            padding: 8px 12px;
            border: 1px solid #ddd;
            color: #512b58;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .pagination a:hover {
            background-color: #f4b083;
            color: #333;
        }
        
        .pagination .active span {
            background-color: #512b58;
            color: white;
            border-color: #512b58;
        }
        
        .pagination .disabled span {
            color: #aaa;
            cursor: not-allowed;
        }
        
        .filters-toggle {
            background-color: rgba(255, 255, 255, 0.9);
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 15px;
            cursor: pointer;
            text-align: center;
            font-weight: bold;
            color: #512b58;
            display: none;
        }
        
        .filters-toggle:hover {
            background-color: #f0f0f0;
        }
        
        .clear-filters {
            background-color: #f4b083;
            color: #333;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s;
        }
        
        .clear-filters:hover {
            background-color: #e9996b;
        }
        
        .actions {
            text-align: center;
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
        }
        
        /* Style pour l'option NLP */
        .nlp-option {
            margin-top: 15px;
            padding: 15px;
            background-color: rgba(241, 241, 241, 0.8);
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        
        .nlp-toggle {
            display: flex;
            align-items: center;
        }
        
        .nlp-toggle input[type="checkbox"] {
            width: auto;
            margin-right: 10px;
        }
        
        .nlp-info {
            margin-top: 10px;
            padding: 10px;
            background-color: rgba(255, 255, 255, 0.7);
            border-radius: 4px;
            font-size: 14px;
            color: green;
        }
        
        .nlp-badge {
            display: inline-block;
            background-color: green;
            color: white;
            border-radius: 12px;
            padding: 3px 10px;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .keywords-list {
            margin-top: 10px;
            padding: 10px;
            background-color: rgba(255, 255, 255, 0.7);
            border-radius: 4px;
            font-size: 14px;
        }
        
        .keywords-list span {
            display: inline-block;
            background-color: #f4b083;
            border-radius: 12px;
            padding: 3px 8px;
            margin: 3px;
            font-size: 12px;
            color: #333;
        }
        
        @media (max-width: 768px) {
            .form-group {
                flex: 100%;
            }
            .resultats {
                grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            }
            .filters-toggle {
                display: block;
            }
            form {
                display: none;
            }
            form.show {
                display: block;
            }
            .actions {
                flex-direction: column;
            }
            .clear-filters {
                margin-left: 0;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <nav>
        <ul>
            <li><a href="#"><i class="fas fa-book"></i> Bibliothèque</a></li>
            <li><a href="#">Accueil</a></li>
            <li><a href="#">Catalogue</a></li>
            <li><a href="#">Nouveautés</a></li>
            <li><a href="#">Contact</a></li>
            <li class="login-btn"><a href="#"><i class="fas fa-user"></i> Connexion</a></li>
        </ul>
    </nav>

    <div class="container">
        <h1>Recherche de livres</h1>
        
        <div class="filters-toggle" id="filtersToggle">
            <i class="fas fa-filter"></i> Afficher les filtres
        </div>
        
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="searchForm">
            <div class="form-row">
                <div class="form-group">
                    <label for="recherche">Recherche par titre ou auteur:</label>
                    <input type="text" id="recherche" name="recherche" value="<?php echo htmlspecialchars($recherche); ?>" placeholder="Entrez un titre ou un auteur...">
                </div>
                
                <div class="form-group">
                    <label for="categorie">Catégorie:</label>
                    <select id="categorie" name="categorie">
                        <option value="">Toutes les catégories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id_categorie']; ?>" <?php echo ($categorie == $cat['id_categorie']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['nom_categorie']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="genre">Genre:</label>
                    <select id="genre" name="genre">
                        <option value="">Tous les genres</option>
                        <?php foreach ($genres as $g): ?>
                            <option value="<?php echo $g['id_genre']; ?>" <?php echo ($genre == $g['id_genre']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($g['nom_genre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="annee">Année de parution:</label>
                    <select id="annee" name="annee">
                        <option value="">Toutes les années</option>
                        <?php foreach ($annees as $a): ?>
                            <option value="<?php echo $a; ?>" <?php echo ($annee == $a) ? 'selected' : ''; ?>>
                                <?php echo $a; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Ajout de l'option pour activer la recherche NLP -->
            <div class="nlp-option">
                <div class="nlp-toggle">
                    <input type="checkbox" id="nlp_active" name="nlp_active" value="1" <?php echo ($nlp_active == '1') ? 'checked' : ''; ?>>
                    <label for="nlp_active">Activer la recherche avancée avec intelligence artificielle</label>
                    <?php if ($nlp_active == '1'): ?>
                        <span class="nlp-badge">Activé</span>
                    <?php endif; ?>
                </div>
                <div class="nlp-info">
                    <i class="fas fa-lightbulb"></i> La recherche avancée utilise le traitement du langage naturel pour trouver des livres liés à votre recherche, même si les termes exacts ne correspondent pas.
                </div>
                
                <?php if ($nlp_active == '1' && !empty($mots_cles)): ?>
                <div class="keywords-list">
                    <strong>Mots-clés identifiés :</strong>
                    <?php foreach ($mots_cles as $mot): ?>
                        <span><?php echo htmlspecialchars($mot); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="actions">
                <button type="submit">
                    <i class="fas fa-search"></i> Rechercher
                </button>
                <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="clear-filters">
                    <i class="fas fa-times"></i> Réinitialiser
                </a>
            </div>
        </form>
        
        <?php if (!empty($message)): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($resultats)): ?>
            <h2>Résultats de la recherche (<?php echo $total_resultats; ?> livre<?php echo $total_resultats > 1 ? 's' : ''; ?> trouvé<?php echo $total_resultats > 1 ? 's' : ''; ?>)</h2>
            
            <div class="resultats">
                 <?php foreach ($resultats as $livre): ?>
                   <div class="livre">
                      <div class="livre-image">
                         <?php if (!empty($livre['image'])): ?>
                            <img src="<?php echo htmlspecialchars($livre['image']); ?>" alt="<?php echo htmlspecialchars($livre['titre'] ?? 'Couverture du livre'); ?>">
                         <?php else: ?>
                            <div class="no-image">Pas d'image disponible</div>
                         <?php endif; ?>
                      </div>
            
                      <div class="livre-content">
                          <h3><?php echo htmlspecialchars($livre['titre'] ?? 'Sans titre'); ?></h3>
                          <p><strong>Auteur:</strong> <?php echo htmlspecialchars($livre['auteur'] ?? 'Non spécifié'); ?></p>
                          <p><strong>Année:</strong> <?php echo $livre['annee_parution'] ?? 'Non spécifiée'; ?></p>
                          <p><strong>Catégorie:</strong> <?php echo htmlspecialchars($livre['nom_categorie'] ?? 'Non spécifiée'); ?></p>
                          <p><strong>Genre:</strong> <?php echo htmlspecialchars($livre['nom_genre'] ?? 'Non spécifié'); ?></p>
                          <p><strong>Description:</strong> <?php echo htmlspecialchars($livre['description'] ?? 'Non spécifiée'); ?></p>
                          <p><strong>Quantité disponible:</strong> <?php echo $livre['quantite'] ?? '0'; ?></p>
                          <p class="info">ID: <?php echo $livre['id_livre']; ?></p>
                      </div>
                   </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                        <li>
                            <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?recherche=<?php echo urlencode($recherche); ?>&categorie=<?php echo urlencode($categorie); ?>&genre=<?php echo urlencode($genre); ?>&annee=<?php echo urlencode($annee); ?>&page=<?php echo $page-1; ?>">
                                <i class="fas fa-chevron-left"></i> Précédent
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="disabled"><span><i class="fas fa-chevron-left"></i> Précédent</span></li>
                    <?php endif; ?>
                    
                    <?php
                    // Afficher au maximum 5 pages
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $start_page + 4);
                    
                    if ($start_page > 1): ?>
                        <li><a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?recherche=<?php echo urlencode($recherche); ?>&categorie=<?php echo urlencode($categorie); ?>&genre=<?php echo urlencode($genre); ?>&annee=<?php echo urlencode($annee); ?>&page=1">1</a></li>
                        <?php if ($start_page > 2): ?>
                            <li class="disabled"><span>...</span></li>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li<?php echo ($i == $page) ? ' class="active"' : ''; ?>>
                            <?php if ($i == $page): ?>
                                <span><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?recherche=<?php echo urlencode($recherche); ?>&categorie=<?php echo urlencode($categorie); ?>&genre=<?php echo urlencode($genre); ?>&annee=<?php echo urlencode($annee); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <li class="disabled"><span>...</span></li>
                        <?php endif; ?>
                        <li><a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?recherche=<?php echo urlencode($recherche); ?>&categorie=<?php echo urlencode($categorie); ?>&genre=<?php echo urlencode($genre); ?>&annee=<?php echo urlencode($annee); ?>&page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a></li>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li>
                            <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?recherche=<?php echo urlencode($recherche); ?>&categorie=<?php echo urlencode($categorie); ?>&genre=<?php echo urlencode($genre); ?>&annee=<?php echo urlencode($annee); ?>&page=<?php echo $page+1; ?>">
                                Suivant <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="disabled"><span>Suivant <i class="fas fa-chevron-right"></i></span></li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script>
        // Script pour afficher/masquer les filtres sur mobile
        document.addEventListener('DOMContentLoaded', function() {
            const filtersToggle = document.getElementById('filtersToggle');
            const searchForm = document.getElementById('searchForm');
            
            if (filtersToggle && searchForm) {
                filtersToggle.addEventListener('click', function() {
                    searchForm.classList.toggle('show');
                    if (searchForm.classList.contains('show')) {
                        filtersToggle.innerHTML = '<i class="fas fa-times"></i> Masquer les filtres';
                    } else {
                        filtersToggle.innerHTML = '<i class="fas fa-filter"></i> Afficher les filtres';
                    }
                });
            }
        });
    </script>
</body>
</html>