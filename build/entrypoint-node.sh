#!/bin/bash
set -e

cd /app
if [ ! -f package.json ]; then
  TMP_APP_DIR="$(mktemp -d)"
  npx -y create-react-app "$TMP_APP_DIR" --use-npm --skip-git
  cp -a "$TMP_APP_DIR"/. /app/
  rm -rf "$TMP_APP_DIR"
fi

if [ ! -d node_modules ]; then
  npm install
fi

npm start
