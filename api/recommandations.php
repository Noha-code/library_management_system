<?php
if (!defined('IN_APP')) {
    die('Accès interdit');
}

/**
 * Système de recommandation de livres
 */
class RecommendationSystem {
    private $db_config;
    private $python_script;

    public function __construct(array $db_config) {
        $this->db_config = $db_config;
        $this->python_script = RECOMMENDATION_SCRIPT;
    }

    /**
     * Obtient des recommandations personnalisées
     * @param int $user_id ID de l'utilisateur
     * @param int $limit Nombre maximum de recommandations
     * @return array Liste des livres recommandés formatés
     * @throws RuntimeException Si la recommandation échoue
     */
    public function getRecommendations(int $user_id, int $limit = 5): array {
        try {
            $python_recs = $this->getPythonRecommendations($user_id, $limit);
            return !empty($python_recs) ? $python_recs : $this->getFallbackRecommendations($user_id, $limit);
        } catch (Exception $e) {
            error_log("Recommendation error: " . $e->getMessage());
            return $this->getPopularBooks($limit);
        }
    }

    private function getPythonRecommendations(int $user_id, int $limit): array {
        $command = sprintf(
            'python3 %s --user_id=%d --limit=%d 2>&1',
            escapeshellarg($this->python_script),
            $user_id,
            $limit
        );

        $output = shell_exec($command);
        $result = json_decode($output ?? '', true);

        if (json_last_error() !== JSON_ERROR_NONE || empty($result['books'])) {
            throw new RuntimeException("Python recommendation failed: " . ($output ?? 'No output'));
        }

        return $this->enrichBookData($result['books']);
    }

    private function getFallbackRecommendations(int $user_id, int $limit): array {
        $conn = connect_db($this->db_config);
        
        $query = "
            SELECT l.id_livre, l.titre, l.auteur, l.annee_parution, l.image, 
                   c.nom_categorie, g.nom_genre
            FROM livres l
            JOIN categorie c ON l.id_categorie = c.id_categorie
            JOIN genre g ON l.id_genre = g.id_genre
            WHERE l.id_livre IN (
                SELECT livre_id FROM emprunts WHERE user_id = :user_id
            )
            LIMIT :limit
        ";

        $stmt = $conn->prepare($query);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $results = $stmt->fetchAll();
        
        if (count($results) >= $limit) {
            return $results;
        }

        $remaining = $limit - count($results);
        $popular = $this->getPopularBooks($remaining);

        return array_merge($results, $popular);
    }

    public function getPopularBooks(int $limit): array {
        $conn = connect_db($this->db_config);
        
        $query = "
            SELECT l.id_livre, l.titre, l.auteur, l.annee_parution, l.image,
                   c.nom_categorie, g.nom_genre, COUNT(e.id) as emprunt_count
            FROM livres l
            LEFT JOIN emprunts e ON l.id_livre = e.livre_id
            JOIN categorie c ON l.id_categorie = c.id_categorie
            JOIN genre g ON l.id_genre = g.id_genre
            GROUP BY l.id_livre
            ORDER BY emprunt_count DESC, l.titre ASC
            LIMIT :limit
        ";

        $stmt = $conn->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private function enrichBookData(array $book_ids): array {
        if (empty($book_ids)) return [];

        $conn = connect_db($this->db_config);
        $placeholders = implode(',', array_fill(0, count($book_ids), '?'));
        
        $query = "
            SELECT l.id_livre, l.titre, l.auteur, l.annee_parution, l.image,
                   c.nom_categorie, g.nom_genre
            FROM livres l
            JOIN categorie c ON l.id_categorie = c.id_categorie
            JOIN genre g ON l.id_genre = g.id_genre
            WHERE l.id_livre IN ($placeholders)
        ";

        $stmt = $conn->prepare($query);
        $stmt->execute($book_ids);

        return $stmt->fetchAll();
    }

    public function saveUserPreferences(int $user_id, array $preferences): bool {
        $conn = connect_db($this->db_config);
        
        try {
            $conn->beginTransaction();

            $conn->prepare("DELETE FROM user_preferences WHERE user_id = ?")
                 ->execute([$user_id]);

            $stmt = $conn->prepare("
                INSERT INTO user_preferences (user_id, preference_type, preference_value, is_encrypted)
                VALUES (?, ?, ?, 1)
            ");

            // Chiffrer et sauvegarder les genres
            foreach ($preferences['genres'] ?? [] as $genre_id) {
                $encrypted_value = encrypt_data($genre_id);
                $stmt->execute([$user_id, 'genre', $encrypted_value]);
            }

            // Chiffrer et sauvegarder les catégories
            foreach ($preferences['categories'] ?? [] as $category_id) {
                $encrypted_value = encrypt_data($category_id);
                $stmt->execute([$user_id, 'category', $encrypted_value]);
            }

            // Chiffrer et sauvegarder les auteurs
            foreach ($preferences['authors'] ?? [] as $author) {
                $encrypted_value = encrypt_data($author);
                $stmt->execute([$user_id, 'author', $encrypted_value]);
            }

            $conn->commit();
            return true;
        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Save preferences failed: " . $e->getMessage());
            return false;
        }
    }

    public function getPreferenceFormData(): array {
        $conn = connect_db($this->db_config);
        
        return [
            'genres' => $conn->query("SELECT id_genre as id, nom_genre as name FROM genre ORDER BY nom_genre")->fetchAll(),
            'categories' => $conn->query("SELECT id_categorie as id, nom_categorie as name FROM categorie ORDER BY nom_categorie")->fetchAll(),
            'authors' => $conn->query("SELECT DISTINCT auteur as name FROM livres ORDER BY auteur")->fetchAll(PDO::FETCH_COLUMN)
        ];
    }

    public function getUserPreferences(int $user_id): array {
        $conn = connect_db($this->db_config);
        
        $stmt = $conn->prepare("
            SELECT preference_type as type, preference_value as value, is_encrypted 
            FROM user_preferences 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        
        $prefs = ['genres' => [], 'categories' => [], 'authors' => []];
        
        while ($row = $stmt->fetch()) {
            // Déchiffrer les valeurs si elles sont chiffrées
            $value = $row['is_encrypted'] ? decrypt_data($row['value']) : $row['value'];
            
            // Pluraliser le type pour correspondre à la structure attendue
            $type = $row['type'] . 's';
            
            if (isset($prefs[$type])) {
                $prefs[$type][] = $value;
            }
        }
        
        return $prefs;
    }

    /**
     * Obtient le chemin vers l'interpréteur Python
     * @return string Chemin de l'interpréteur Python
     */
    public function getPythonPath(): string {
        return PYTHON_PATH;
    }

    /**
     * Obtient le chemin vers le script de recommandation Python
     * @return string Chemin du script Python
     */
    public function getScriptPath(): string {
        return $this->python_script;
    }
}

function connect_db(array $config): PDO {
    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['dbname'],
            $config['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false
        ];

        return new PDO($dsn, $config['user'], $config['password'], $options);
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        throw new RuntimeException('Database connection error. Please try again later.');
    }
}