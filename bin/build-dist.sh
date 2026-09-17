#!/usr/bin/env bash
# Build the wp.org distribution into dist/ (SVN trunk == dist/ output).
set -euo pipefail
cd "$(dirname "$0")/.."

rm -rf dist
mkdir -p dist

npm run build

# rsync everything except .distignore entries.
rsync -rc --delete-after \
	--exclude-from=.distignore \
	./ dist/

echo "dist/ ready: $(find dist -type f | wc -l | tr -d ' ') files"
