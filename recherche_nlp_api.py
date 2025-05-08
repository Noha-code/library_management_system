from flask import Flask, request, jsonify
import spacy

app = Flask(__name__)

# Charger le modèle spaCy en français
nlp = spacy.load("fr_core_news_sm")

# Dictionnaire de synonymes simples (tu peux l'enrichir)
synonymes = {
    # Synonymes basés sur les titres des livres
    "petit prince": ["prince", "étoile", "rose", "renard", "apprivoiser"],
    "1984": ["big brother", "surveillance", "totalitarisme", "newspeak", "orwell"],
    "temps": ["histoire", "cosmologie", "univers", "espace", "relativité"],
    "sapiens": ["homo sapiens", "humanité", "évolution", "histoire humaine", "anthropologie"],
    "sophie": ["philosophie", "monde", "sagesse", "connaissance", "questionnement"],
    "étranger": ["absurde", "indifférence", "soleil", "meurtre", "camus"],
    "fahrenheit": ["livre", "censure", "feu", "dystopie", "résistance"],
    "meilleur des mondes": ["utopie", "fordisme", "soma", "conditionnement", "clonage"],
    "cosmos": ["univers", "étoile", "galaxie", "espace", "astronomie"],
    "geisha": ["japon", "tradition", "art", "culture", "mémoire"],
    "guerre": ["stratégie", "art", "tactique", "conflit", "victoire"],
    "pensées": ["pascal", "philosophie", "dieu", "raison", "foi"],
    "raison pure": ["kant", "métaphysique", "entendement", "critique", "transcendantal"],
    "république": ["platon", "justice", "cité", "politique", "philosophie"],
    "iliade": ["troie", "achille", "hector", "guerre", "homère"],
    "odyssée": ["ulysse", "voyage", "ithaque", "sirènes", "cyclope"],
    "rose": ["roman", "medieval", "mystère", "bibliothèque", "nom"],
    "méditations": ["descartes", "méthode", "doute", "existence", "cogito"],
    "condition humaine": ["révolution", "chine", "existence", "engagement", "malraux"]
}
@app.route('/analyser', methods=['POST'])
def analyser():
    data = request.json
    texte = data.get("texte", "")
    
    doc = nlp(texte.lower())
    mots_cles = set()
    
    for token in doc:
        if not token.is_stop and not token.is_punct:
            mots_cles.add(token.lemma_)
            mots_cles.update(synonymes.get(token.lemma_, []))

    return jsonify({"mots_cles": list(mots_cles)})

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5001)
