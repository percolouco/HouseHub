#!/bin/bash
echo "🔧 Installation de l'environnement de développement..."

# Installer les aliases Git
source .devtools/git-aliases.sh

# Ajouter .devtools au gitignore local (pas versionné)
echo "/.devtools/" >> .git/info/exclude

echo "✅ Installation terminée !"
echo "ℹ️  Les outils de dev sont maintenant ignorés localement"
echo "🚀 Vous pouvez maintenant utiliser: git deploy staging"
