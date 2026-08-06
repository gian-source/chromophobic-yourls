#!/usr/bin/env bash

set -euo pipefail

REMOTE="hostpoint"
REMOTE_PATH="/home/gebhard1/www/go.chromophobic.ch/"
CURRENT_BRANCH="$(git branch --show-current)"

if [[ "$CURRENT_BRANCH" != "main" ]]; then
    echo "Abbruch: Deployment ist nur vom Branch 'main' erlaubt."
    echo "Aktueller Branch: $CURRENT_BRANCH"
    exit 1
fi

if [[ -n "$(git status --porcelain)" ]]; then
    echo "Abbruch: Es gibt noch nicht committete Änderungen."
    echo
    git status --short
    exit 1
fi

RSYNC_OPTIONS=(
    -avz
    --delete
    --exclude=.git/
    --exclude=.github/
    --exclude=.vscode/
    --exclude=.DS_Store
    --exclude=.htaccess
    --exclude=user/config.php
    --exclude=deploy.sh
    --exclude=tests/
    --exclude=phpunit.xml.dist
    --exclude=README.md
    --exclude=CHANGELOG.md
    --exclude=readme.html
    --exclude=sample-*
    --exclude=composer.json
    --exclude=composer.lock
    --exclude=.editorconfig
    --exclude=.gitattributes
    --exclude=.gitignore
    --exclude=.git-blame-ignore-revs
)

echo "Vorschau des Deployments:"
echo

rsync "${RSYNC_OPTIONS[@]}" --dry-run ./ "$REMOTE:$REMOTE_PATH"

echo
read -r -p "Deployment wirklich ausführen? [y/N] " ANSWER

if [[ ! "$ANSWER" =~ ^[Yy]$ ]]; then
    echo "Deployment abgebrochen."
    exit 0
fi

echo
echo "Deployment wird ausgeführt ..."

rsync "${RSYNC_OPTIONS[@]}" ./ "$REMOTE:$REMOTE_PATH"

echo
echo "Deployment abgeschlossen:"
echo "https://go.chromophobic.ch"
