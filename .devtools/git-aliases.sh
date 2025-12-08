#!/bin/bash
echo "🔧 Installation des commandes Git personnalisées..."

# Commande deploy
git config --global alias.deploy '!f() {
    current_branch=$(git branch --show-current);
    echo "📍 Branche actuelle: $current_branch";
    echo "🔍 État avant auto-commit:";
    git status --short;
    
    # Auto-commit des modifications si nécessaire
    if ! git diff-index --quiet HEAD --; then
        echo "💾 Auto-commit des modifications sur $current_branch...";
        git add .;
        git commit -m "Auto-commit for deploy to $1 - $(date +%Y-%m-%d\ %H:%M:%S)";
        echo "✅ Modifications commitées sur $current_branch !";
    else
        echo "ℹ️  Pas de modifications à commiter sur $current_branch";
    fi;
    
    echo "🔍 État après auto-commit:";
    git status --short;
    echo "🔍 Dernier commit sur $current_branch:";
    git log --oneline -1;
    
    if [ "$1" = "staging" ]; then
        echo "🚀 Push de $current_branch vers deploiement-staging...";
        git push origin $current_branch:deploiement-staging --force;
        echo "✅ Staging mis à jour avec le contenu de $current_branch !";
    elif [ "$1" = "production" ] || [ "$1" = "prod" ]; then
        echo "🚀 Déploiement en production...";
        git checkout main;
        git pull origin main;
        git merge $current_branch -m "Deploy from $current_branch to production";
        git push origin main;
        git checkout $current_branch;
    else
        echo "❌ Usage: git deploy [staging|production]";
        return 1;
    fi;
    
    echo "🔍 Vous êtes toujours sur: $(git branch --show-current)";
    echo "✅ Déployé sur $1 !";
}; f'

# Commande publish
git config --global alias.publish '!f() {
    current_branch=$(git branch --show-current);
    git push origin $current_branch;
    echo "✅ Branche $current_branch publiée !";
    echo "👉 Créez votre PR dans Gitea : $current_branch → main";
}; f'

# Commande cleanup
git config --global alias.cleanup '!f() {
    current_branch=$(git branch --show-current);
    git checkout main;
    git pull origin main;
    git branch -d $current_branch 2>/dev/null;
    git push origin --delete $current_branch 2>/dev/null;
    echo "🧹 Nettoyage terminé !";
}; f'

echo "✅ Commandes Git installées !"
echo "📖 Utilisation :"
echo "   git deploy staging     # → Teste sur staging"
echo "   git deploy production  # → Déploie en prod"
echo "   git publish           # → Publie pour PR"
echo "   git cleanup           # → Nettoie après merge"
