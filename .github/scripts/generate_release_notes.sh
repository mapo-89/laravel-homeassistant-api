#!/bin/bash
set -e

VERSION=${GITHUB_REF_NAME:-v1.4.0} 
PREVIOUS_TAG=$(git tag --sort=-creatordate | grep -v "^$VERSION$" | head -n 1)

if [ -z "$PREVIOUS_TAG" ]; then
  PREVIOUS_TAG=$(git rev-list --max-parents=0 HEAD)
fi

declare -A TYPES=(
  ["✨ Feature:"]="✨ Features:"
  ["🐛 Fix:"]="🐛 Fixes:"
  ["♻️ Refactoring:"]="♻️ Refactoring:"
  ["📝 Docs:"]="📝 Documentation:"
  ["⚠️ Deprecated:"]="⚠️ Deprecated:"
  ["🔥 Remove:"]="🔥 Removed:"
  ["🔧 Chore:"]="🔧 Chores:"
  ["🚀 Deploy:"]="🚀 Deployment:"
)

RELEASE_NOTES="Release Notes for $VERSION\n\n"

for PREFIX in "${!TYPES[@]}"; do
  HEADER="${TYPES[$PREFIX]}"
  COMMITS=$(git log "$PREVIOUS_TAG"..HEAD --pretty=format:'%s' | grep "^$PREFIX" || true)
  if [ -n "$COMMITS" ]; then
    RELEASE_NOTES+="$HEADER"$'\n'
    while IFS= read -r COMMIT; do
      MESSAGE=$(echo "$COMMIT" | sed -E "s/^$PREFIX[[:space:]]*//")
      RELEASE_NOTES+="- $MESSAGE"$'\n'
    done <<< "$COMMITS"
    RELEASE_NOTES+=$'\n'
  fi
done

echo -e "$RELEASE_NOTES"
