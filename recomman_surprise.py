import pandas as pd
import numpy as np
import pymysql
from surprise import Dataset, Reader, SVD, KNNBasic
from surprise.model_selection import train_test_split
from collections import defaultdict
import logging

class LibraryRecommender:
    def __init__(self, db_config):
        """
        Initialisation du système de recommandation
        
        Args:
            db_config: Configuration de la base de données MySQL
                {
                    'host': 'localhost',
                    'user': 'username',
                    'password': 'password',
                    'db': 'library',
                    'charset': 'utf8mb4'
                }
        """
        self.db_config = db_config
        self.conn = None
        self.models = {}
        self.user_preferences = {}  # Pour stocker les préférences explicites des utilisateurs
        
        # Configuration du logger
        logging.basicConfig(level=logging.INFO)
        self.logger = logging.getLogger('LibraryRecommender')
        
        # Se connecter à la base de données
        self.connect_to_db()
    
    def connect_to_db(self):
        """Établit une connexion à la base de données MySQL"""
        try:
            self.conn = pymysql.connect(**self.db_config)
            self.logger.info("Connexion à la base de données établie avec succès")
        except Exception as e:
            self.logger.error(f"Erreur lors de la connexion à la base de données: {e}")
            raise
    
    def fetch_data(self):
        """
        Récupère toutes les données nécessaires de la base de données
        """
        # Récupérer les données d'emprunt (interactions utilisateur-livre)
        loan_data = self._fetch_loan_data()
        
        # Récupérer les données de recherche
        search_data = self._fetch_search_data()
        
        # Récupérer les métadonnées des livres
        books_data = self._fetch_books_data()
        
        # Récupérer les données utilisateurs
        users_data = self._fetch_users_data()
        
        return {
            'loans': loan_data,
            'searches': search_data,
            'books': books_data,
            'users': users_data
        }
    
    def _fetch_loan_data(self):
        """Version corrigée pour votre schéma"""
        query = """
        SELECT user_id as utilisateur_id,  # Adaptation au schéma réel
               livre_id, 
               DATEDIFF(date_limite, date_emprunt) as duree_emprunt 
        FROM emprunts
        """
        try:
            with self.conn.cursor() as cursor:
                cursor.execute(query)
                result = cursor.fetchall()
                df = pd.DataFrame(result, columns=['utilisateur_id', 'livre_id', 'duree_emprunt'])
                return df
        except Exception as e:
            self.logger.error(f"Erreur lors de la récupération des données d'emprunt: {e}")
            raise
    
    def _load_preferences_from_db(self, user_id):
        """Charge les préférences depuis la table user_preferences"""
        query = """
        SELECT preference_type, preference_value 
        FROM user_preferences 
        WHERE user_id = %s
        """
        try:
            with self.conn.cursor() as cursor:
                cursor.execute(query, (user_id,))
                result = cursor.fetchall()
                
                prefs = {'genres': [], 'categories': [], 'authors': []}
                for row in result:
                    if row[0] == 'genre':
                        prefs['genres'].append(int(row[1]))
                    elif row[0] == 'category':
                        prefs['categories'].append(int(row[1]))
                    elif row[0] == 'author':
                        prefs['authors'].append(row[1])
                
                return prefs
        except Exception as e:
            self.logger.error(f"Erreur chargement préférences: {e}")
            return None

    def _fetch_search_data(self):
        """Récupère l'historique des recherches des utilisateurs"""
        query = """
        SELECT user_id, search_query, category_id, genre_id
        FROM search_history
        """
        try:
            with self.conn.cursor() as cursor:
                cursor.execute(query)
                result = cursor.fetchall()
                df = pd.DataFrame(result, columns=['user_id', 'search_query', 'category_id', 'genre_id'])
                return df
        except Exception as e:
            self.logger.error(f"Erreur lors de la récupération des données de recherche: {e}")
            raise
    
    def _fetch_books_data(self):
        """Récupère les métadonnées des livres"""
        query = """
        SELECT id_livre, titre, auteur, annee_parution, id_categorie, id_genre
        FROM livres
        """
        try:
            with self.conn.cursor() as cursor:
                cursor.execute(query)
                result = cursor.fetchall()
                df = pd.DataFrame(result, columns=['id_livre', 'titre', 'auteur', 'annee_parution', 'id_categorie', 'id_genre'])
                return df
        except Exception as e:
            self.logger.error(f"Erreur lors de la récupération des métadonnées des livres: {e}")
            raise
    
    def _fetch_users_data(self):
        """Récupère les données des utilisateurs"""
        query = """
        SELECT id, username, first_name, last_name
        FROM users
        """
        try:
            with self.conn.cursor() as cursor:
                cursor.execute(query)
                result = cursor.fetchall()
                df = pd.DataFrame(result, columns=['id', 'username', 'first_name', 'last_name'])
                return df
        except Exception as e:
            self.logger.error(f"Erreur lors de la récupération des données utilisateurs: {e}")
            raise
    
    def preprocess_data(self, data):
        """
        Prétraitement des données pour créer un ensemble de données adapté à Surprise
        
        Args:
            data: Dictionnaire contenant toutes les données récupérées
            
        Returns:
            Données prétraitées pour l'algorithme de recommandation
        """
        # Préparation des données d'emprunt comme source principale d'interactions
        loans_df = data['loans'].copy()
        
        # Normaliser la durée d'emprunt comme un indicateur d'intérêt (plus longtemps = plus d'intérêt)
        # Limite max à 30 jours pour éviter les valeurs aberrantes
        loans_df['duree_emprunt'] = loans_df['duree_emprunt'].clip(0, 30)
        max_duree = loans_df['duree_emprunt'].max()
        
        if max_duree > 0:
            loans_df['rating'] = 3 + 2 * (loans_df['duree_emprunt'] / max_duree)
        else:
            loans_df['rating'] = 3  # Valeur par défaut
        
        # Créer un dataframe pour Surprise
        ratings_df = loans_df[['utilisateur_id', 'livre_id', 'rating']]
        
        # Ajouter des ratings implicites basés sur les recherches
        search_ratings = self._process_search_data(data['searches'])
        if not search_ratings.empty:
            ratings_df = pd.concat([ratings_df, search_ratings], ignore_index=True)
        
        return ratings_df
    
    def _process_search_data(self, search_df):
        """
        Transforme les données de recherche en ratings implicites
        """
        if search_df.empty:
            return pd.DataFrame()
        
        # Compter le nombre de recherches par utilisateur et genre/catégorie
        genre_counts = search_df.groupby(['user_id', 'genre_id']).size().reset_index(name='count')
        genre_counts = genre_counts[genre_counts['genre_id'].notna()]
        
        # Récupérer les livres correspondant à chaque genre
        books_by_genre = {}
        try:
            with self.conn.cursor() as cursor:
                for _, row in genre_counts.iterrows():
                    genre_id = int(row['genre_id'])
                    query = f"SELECT id_livre FROM livres WHERE id_genre = {genre_id}"
                    cursor.execute(query)
                    books = cursor.fetchall()
                    books_by_genre[(row['user_id'], genre_id)] = [book[0] for book in books]
        except Exception as e:
            self.logger.error(f"Erreur lors de la récupération des livres par genre: {e}")
            return pd.DataFrame()
        
        # Créer des ratings implicites (faibles) basés sur les recherches
        search_ratings = []
        for (user_id, genre_id), count in genre_counts[['user_id', 'genre_id', 'count']].itertuples(index=False):
            if (user_id, genre_id) in books_by_genre:
                for book_id in books_by_genre[(user_id, genre_id)]:
                    # Rating plus faible (2.5-3.5) car implicite, augmente avec le nombre de recherches
                    normalized_count = min(count / 10, 1)  # Normaliser entre 0 et 1
                    rating = 2.5 + normalized_count  # Entre 2.5 et 3.5
                    search_ratings.append({
                        'utilisateur_id': user_id,
                        'livre_id': book_id,
                        'rating': rating
                    })
        
        return pd.DataFrame(search_ratings)
    
    def store_user_preferences(self, user_id, preferences):
        """
        Stocke les préférences explicites de l'utilisateur
        
        Args:
            user_id: ID de l'utilisateur
            preferences: Dictionnaire contenant les préférences 
                {
                    'genres': [1, 3],  # IDs des genres préférés
                    'categories': [2, 4],  # IDs des catégories préférées
                    'authors': ['Stephen Hawking', 'George Orwell'],  # Auteurs préférés 
                }
        """
        self.user_preferences[user_id] = preferences
        
        # Enregistrer ces préférences en base de données
        try:
            with self.conn.cursor() as cursor:
                # D'abord supprimer les anciennes préférences
                cursor.execute("DELETE FROM user_preferences WHERE user_id = %s", (user_id,))
                
                # Insérer les nouvelles préférences
                if 'genres' in preferences and preferences['genres']:
                    for genre_id in preferences['genres']:
                        cursor.execute(
                            "INSERT INTO user_preferences (user_id, preference_type, preference_value) VALUES (%s, %s, %s)",
                            (user_id, 'genre', genre_id)
                        )
                
                if 'categories' in preferences and preferences['categories']:
                    for category_id in preferences['categories']:
                        cursor.execute(
                            "INSERT INTO user_preferences (user_id, preference_type, preference_value) VALUES (%s, %s, %s)",
                            (user_id, 'category', category_id)
                        )
                
                if 'authors' in preferences and preferences['authors']:
                    for author in preferences['authors']:
                        cursor.execute(
                            "INSERT INTO user_preferences (user_id, preference_type, preference_value) VALUES (%s, %s, %s)",
                            (user_id, 'author', author)
                        )
                
                self.conn.commit()
        except Exception as e:
            self.logger.error(f"Erreur lors de l'enregistrement des préférences: {e}")
            self.conn.rollback()
    
    def train_models(self, ratings_df):
        """
        Entraîne les modèles de recommandation
        
        Args:
            ratings_df: DataFrame contenant les évaluations (utilisateur_id, livre_id, rating)
        """
        # Créer un objet Reader de Surprise
        reader = Reader(rating_scale=(1, 5))
        
        # Créer un Dataset Surprise à partir du DataFrame
        data = Dataset.load_from_df(ratings_df[['utilisateur_id', 'livre_id', 'rating']], reader)
        
        # Diviser les données en ensemble d'entraînement et de test
        trainset, testset = train_test_split(data, test_size=0.2)
        
        # Entraîner un modèle SVD (Matrix Factorization)
        self.logger.info("Entraînement du modèle SVD...")
        model_svd = SVD(n_factors=100, n_epochs=20, reg_all=0.02)
        model_svd.fit(trainset)
        self.models['svd'] = model_svd
        
        # Entraîner un modèle KNN (K-Nearest Neighbors)
        self.logger.info("Entraînement du modèle KNN...")
        model_knn = KNNBasic(k=40, sim_options={'name': 'pearson_baseline', 'user_based': False})
        model_knn.fit(trainset)
        self.models['knn'] = model_knn
        
        self.logger.info("Entraînement des modèles terminé avec succès")
    
    def get_recommendations(self, user_id, n=10):
        """
        Génère des recommandations personnalisées pour un utilisateur
        
        Args:
            user_id: ID de l'utilisateur
            n: Nombre de recommandations à générer
            
        Returns:
            Liste des livres recommandés avec leurs scores
        """
        # Charger les préférences depuis la DB avant de générer les recommandations
        prefs = self._load_preferences_from_db(user_id)
        if prefs:
            self.store_user_preferences(user_id, prefs)
        
        if not self.models:
            self.logger.error("Les modèles ne sont pas entraînés. Veuillez exécuter train_models() d'abord.")
            return []
        
        # Récupérer tous les livres
        books_df = self._fetch_books_data()
        
        # Récupérer les livres déjà empruntés par l'utilisateur
        borrowed_books = self._get_user_borrowed_books(user_id)
        
        # Candidats pour recommandation (livres non empruntés)
        candidates = [book_id for book_id in books_df['id_livre'].unique() if book_id not in borrowed_books]
        
        if not candidates:
            self.logger.warning(f"Aucun livre candidat trouvé pour l'utilisateur {user_id}")
            return []
        
        # Prédire les notes avec le modèle SVD
        predictions = []
        for book_id in candidates:
            pred = self.models['svd'].predict(user_id, book_id)
            predictions.append((book_id, pred.est))
        
        # Trier par estimation décroissante
        predictions.sort(key=lambda x: x[1], reverse=True)
        
        # Appliquer le filtrage basé sur les préférences explicites
        filtered_recommendations = self._filter_by_preferences(user_id, predictions, books_df)
        
        # Prendre les n meilleures recommandations
        top_recommendations = filtered_recommendations[:n]
        
        # Enrichir les recommandations avec les détails des livres
        result = []
        for book_id, score in top_recommendations:
            book_info = books_df[books_df['id_livre'] == book_id].iloc[0].to_dict()
            book_info['score'] = score
            result.append(book_info)
        
        return result
    
    def _get_user_borrowed_books(self, user_id):
        """Récupère les livres déjà empruntés par l'utilisateur"""
        query = """
        SELECT livre_id FROM emprunts WHERE utilisateur_id = %s
        """
        try:
            with self.conn.cursor() as cursor:
                cursor.execute(query, (user_id,))
                result = cursor.fetchall()
                return [r[0] for r in result]
        except Exception as e:
            self.logger.error(f"Erreur lors de la récupération des livres empruntés: {e}")
            return []
    
    def _filter_by_preferences(self, user_id, predictions, books_df):
        """
        Filtre et réordonne les recommandations en fonction des préférences explicites
        """
        if user_id not in self.user_preferences:
            return predictions
        
        preferences = self.user_preferences[user_id]
        
        # Créer une fonction de boost basée sur les préférences
        def preference_boost(book_id):
            book = books_df[books_df['id_livre'] == book_id]
            if book.empty:
                return 0
            
            boost = 0
            book_info = book.iloc[0]
            
            # Boost pour les genres préférés
            if 'genres' in preferences and preferences['genres']:
                if book_info['id_genre'] in preferences['genres']:
                    boost += 0.5
            
            # Boost pour les catégories préférées
            if 'categories' in preferences and preferences['categories']:
                if book_info['id_categorie'] in preferences['categories']:
                    boost += 0.5
            
            # Boost pour les auteurs préférés
            if 'authors' in preferences and preferences['authors']:
                if book_info['auteur'] in preferences['authors']:
                    boost += 0.7
            
            return boost
        
        # Appliquer le boost aux prédictions
        boosted_predictions = [(book_id, score + preference_boost(book_id)) 
                              for book_id, score in predictions]
        
        # Retrier les prédictions
        boosted_predictions.sort(key=lambda x: x[1], reverse=True)
        
        return boosted_predictions
    
    def create_preference_form(self):
        """
        Génère les données nécessaires pour créer un formulaire de préférences utilisateur
        
        Returns:
            Dictionnaire contenant les genres, catégories et auteurs disponibles
        """
        genres = self._fetch_genres()
        categories = self._fetch_categories()
        authors = self._fetch_authors()
        
        return {
            'genres': genres,
            'categories': categories,
            'authors': authors
        }
    
    def _fetch_genres(self):
        """Récupère tous les genres disponibles"""
        query = "SELECT id_genre, nom_genre FROM genre"
        try:
            with self.conn.cursor() as cursor:
                cursor.execute(query)
                result = cursor.fetchall()
                return [{'id': r[0], 'name': r[1]} for r in result]
        except Exception as e:
            self.logger.error(f"Erreur lors de la récupération des genres: {e}")
            return []
    
    def _fetch_categories(self):
        """Récupère toutes les catégories disponibles"""
        query = "SELECT id_categorie, nom_categorie FROM categorie"
        try:
            with self.conn.cursor() as cursor:
                cursor.execute(query)
                result = cursor.fetchall()
                return [{'id': r[0], 'name': r[1]} for r in result]
        except Exception as e:
            self.logger.error(f"Erreur lors de la récupération des catégories: {e}")
            return []
    
    def _fetch_authors(self):
        """Récupère tous les auteurs disponibles"""
        query = "SELECT DISTINCT auteur FROM livres ORDER BY auteur"
        try:
            with self.conn.cursor() as cursor:
                cursor.execute(query)
                result = cursor.fetchall()
                return [r[0] for r in result]
        except Exception as e:
            self.logger.error(f"Erreur lors de la récupération des auteurs: {e}")
            return []
    
    # Ajouter cette méthode à la classe LibraryRecommender
    def generate_final_recommendations(self, user_id, n=10):
        """
        Génère des recommandations finales en combinant les préférences utilisateur,
        l'historique des emprunts et l'historique des recherches
        
        Args:
            user_id: ID de l'utilisateur
            n: Nombre de recommandations à générer (défaut: 10)
            
        Returns:
            Liste des livres recommandés avec leurs scores et un résumé des facteurs d'influence
        """
        # Récupérer les données de l'utilisateur
        prefs = self._load_preferences_from_db(user_id)
        if prefs:
            self.store_user_preferences(user_id, prefs)
        
        loans = self._get_user_borrowed_books(user_id)
        searches_df = self._fetch_search_data()
        user_searches = searches_df[searches_df['user_id'] == user_id]
        
        # Créer le dictionnaire de données combinées
        combined_data = {
            'preferences': prefs if prefs else {'genres': [], 'categories': [], 'authors': []},
            'loan_history': loans,
            'search_history': user_searches
        }
        
        # Vérifier que les modèles sont entraînés
        if not self.models:
            self.logger.error("Les modèles ne sont pas entraînés. Veuillez exécuter train_models() d'abord.")
            return []
        
        # Récupérer tous les livres
        books_df = self._fetch_books_data()
        
        # Candidats pour recommandation (livres non empruntés)
        candidates = [book_id for book_id in books_df['id_livre'].unique() if book_id not in loans]
        
        if not candidates:
            self.logger.warning(f"Aucun livre candidat trouvé pour l'utilisateur {user_id}")
            return []
        
        # Prédire les notes avec le modèle SVD
        predictions = []
        for book_id in candidates:
            pred = self.models['svd'].predict(user_id, book_id)
            predictions.append((book_id, pred.est))
        
        # Trier par estimation décroissante
        predictions.sort(key=lambda x: x[1], reverse=True)
        
        # Intégrer les préférences explicites et l'historique de recherche
        enhanced_recommendations = self._enhance_with_combined_data(user_id, predictions, books_df, combined_data)
        
        # Prendre les n meilleures recommandations
        top_recommendations = enhanced_recommendations[:n]
        
        # Enrichir les recommandations avec les détails des livres
        result = []
        for book_id, score, factors in top_recommendations:
            book_info = books_df[books_df['id_livre'] == book_id].iloc[0].to_dict()
            book_info['score'] = score
            book_info['factors'] = factors  # Ajouter les facteurs d'influence
            result.append(book_info)
        
        return result
        
    def _enhance_with_combined_data(self, user_id, predictions, books_df, combined_data):
        """
        Améliore les recommandations en utilisant les données combinées de l'utilisateur
        
        Args:
            user_id: ID de l'utilisateur
            predictions: Liste de tuples (book_id, score)
            books_df: DataFrame des livres
            combined_data: Dictionnaire contenant les données combinées
            
        Returns:
            Liste améliorée de tuples (book_id, score, factors)
        """
        enhanced_predictions = []
        
        # Extraire les préférences du dictionnaire
        preferences = combined_data['preferences']
        
        # Analyser l'historique de recherche
        search_interests = self._extract_search_interests(combined_data['search_history'])
        
        for book_id, base_score in predictions:
            book = books_df[books_df['id_livre'] == book_id]
            if book.empty:
                continue
                
            book_info = book.iloc[0]
            factors = []  # Pour expliquer les facteurs influençant la recommandation
            boost = 0
            
            # Appliquer les boosts en fonction des préférences
            if 'genres' in preferences and preferences['genres']:
                if book_info['id_genre'] in preferences['genres']:
                    boost += 0.5
                    factors.append(f"Genre préféré (+0.5)")
            
            if 'categories' in preferences and preferences['categories']:
                if book_info['id_categorie'] in preferences['categories']:
                    boost += 0.5
                    factors.append(f"Catégorie préférée (+0.5)")
            
            if 'authors' in preferences and preferences['authors']:
                if book_info['auteur'] in preferences['authors']:
                    boost += 0.7
                    factors.append(f"Auteur préféré (+0.7)")
            
            # Boost en fonction des recherches
            if search_interests.get('genres') and book_info['id_genre'] in search_interests['genres']:
                genre_boost = 0.3 * search_interests['genres'][book_info['id_genre']] / 10
                boost += genre_boost
                factors.append(f"Intérêt de recherche pour le genre (+{genre_boost:.2f})")
            
            if search_interests.get('categories') and book_info['id_categorie'] in search_interests['categories']:
                cat_boost = 0.3 * search_interests['categories'][book_info['id_categorie']] / 10
                boost += cat_boost
                factors.append(f"Intérêt de recherche pour la catégorie (+{cat_boost:.2f})")
            
            # Calcul du score final
            final_score = base_score + boost
            
            # Ajouter le score de base aux facteurs
            factors.insert(0, f"Score de base: {base_score:.2f}")
            
            enhanced_predictions.append((book_id, final_score, factors))
        
        # Trier par score final décroissant
        enhanced_predictions.sort(key=lambda x: x[1], reverse=True)
        
        return enhanced_predictions
        
    def _extract_search_interests(self, search_history):
        """
        Extrait les intérêts de l'utilisateur à partir de son historique de recherche
        
        Args:
            search_history: DataFrame de l'historique de recherche
            
        Returns:
            Dictionnaire des intérêts (genres, catégories, etc.)
        """
        interests = {'genres': {}, 'categories': {}}
        
        if search_history.empty:
            return interests
        
        # Compter les occurrences de chaque genre dans les recherches
        genre_counts = search_history['genre_id'].value_counts().to_dict()
        for genre_id, count in genre_counts.items():
            if pd.notna(genre_id):  # Vérifier que le genre n'est pas NaN
                interests['genres'][int(genre_id)] = count
        
        # Compter les occurrences de chaque catégorie dans les recherches
        category_counts = search_history['category_id'].value_counts().to_dict()
        for cat_id, count in category_counts.items():
            if pd.notna(cat_id):  # Vérifier que la catégorie n'est pas NaN
                interests['categories'][int(cat_id)] = count
        
        return interests

# Exemple d'utilisation
if __name__ == "__main__":
    # Configuration de la base de données
    db_config = {
        'host': 'localhost',
        'user': 'root',
        'password': '',
        'db': 'library',
        'charset': 'utf8mb4'
    }
    
    # Créer une instance du recommandeur
    recommender = LibraryRecommender(db_config)
    
    # Récupérer les données
    data = recommender.fetch_data()
    
    # Prétraiter les données
    ratings_df = recommender.preprocess_data(data)
    
    # Entraîner les modèles
    recommender.train_models(ratings_df)
    
    # Stocker les préférences d'un utilisateur (exemple)
    user_preferences = {
        'genres': [2, 3],  # Science-Fiction et Biographie
        'categories': [1, 2],  # Roman et Science
        'authors': ['George Orwell', 'Yuval Noah Harari']
    }
    recommender.store_user_preferences(1, user_preferences)
    
    # Générer des recommandations
    recommendations = recommender.get_recommendations(1, n=5)
    
    print("Livres recommandés:")
    for i, book in enumerate(recommendations, 1):
        print(f"{i}. {book['titre']} par {book['auteur']} (Score: {book['score']:.2f})")