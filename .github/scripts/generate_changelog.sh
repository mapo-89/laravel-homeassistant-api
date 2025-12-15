#!/bin/bash
set -e

# -------------------------------
# Variablen
# -------------------------------
VERSION=${GITHUB_REF_NAME:-v1.4.0}        
DATE=$(date +'%Y-%m-%d')
ROOT_CHANGELOG="CHANGELOG.md" 

# Letztes Tag ermitteln
PREVIOUS_TAG=$(git tag --sort=-creatordate | grep -v "^$VERSION$" | head -n 1)
if [ -z "$PREVIOUS_TAG" ]; then
  PREVIOUS_TAG=$(git rev-list --max-parents=0 HEAD)
fi

declare -A TYPES=(
    ["✨ Feature:"]="Added"
    ["🛠️ Tool:"]="Added"
    ["🗃️ DB:"]="Added"
    ["🛣️ Routes:"]="Added"
    ["💄 UI:"]="Added"
    ["♻️ Refactoring:"]="Changed"
    ["🔤 Text:"]="Changed"
    ["🎨 Styling:"]="Changed"
    ["⚠️ Deprecated:"]="Deprecated"
    ["🔥 Remove:"]="Removed"
    ["🚚 Move:"]="Removed"
    ["🐛 Fix:"]="Fixed"
    ["🚑 Hotfix:"]="Fixed"
    ["🔒 Security:"]="Security"
    ["🛡️ Security:"]="Security"
    ["⚡️ Performance:"]="Performance"
    ["📊 Logs:"]="Performance"
    ["📝 Docs:"]="Documentation"
    ["📚 Docs:"]="Documentation"
    ["🌐 i18n:"]="Documentation"
    ["🔧 Chore:"]="Chores"
    ["📦 Deps:"]="Chores"
    ["⬆️ Deps:"]="Chores"
    ["⬇️ Deps:"]="Chores"
    ["🚀 Deploy:"]="Deployment"
    ["🔖 Release:"]="Deployment"
    ["🎉 Init:"]="Miscellaneous"
    ["✏️ Typo:"]="Miscellaneous"
    ["🙈 Gitignore:"]="Miscellaneous"
    ["🔀 Merge:"]="Miscellaneous"
)

declare -A SECTIONS
for S in Added Changed Fixed Deprecated Removed Security Performance Documentation Chores Deployment; do
  SECTIONS[$S]=''
done

# Commit
for PREFIX in "${!TYPES[@]}"; do
  SECTION="${TYPES[$PREFIX]}"
  COMMITS=$(git log "$PREVIOUS_TAG..HEAD" --pretty=format:'%s' | grep "^$PREFIX" || true)
  if [ -n "$COMMITS" ]; then
    while IFS= read -r COMMIT; do
      MESSAGE=$(echo "$COMMIT" | sed -E "s/^$PREFIX[[:space:]]*//")
      SECTIONS[$SECTION]+="- $MESSAGE"$'\n'
    done <<< "$COMMITS"
  fi
done

# Changelog
CHANGELOG="# Changelog\n\nAll notable changes to \`laravel-homeassistant-api\` will be documented in this file\n\n"
CHANGELOG+="## [$VERSION] - $DATE"$'\n\n'
ORDER=(Added Changed Fixed Deprecated Removed Security Performance Documentation Chores Deployment)
for SECTION in "${ORDER[@]}"; do
  if [ -n "${SECTIONS[$SECTION]}" ]; then
    CHANGELOG+="### $SECTION"$'\n'
    CHANGELOG+="${SECTIONS[$SECTION]}"$'\n'
  fi
done

TMP=$(mktemp)
echo -e "$CHANGELOG" > "$TMP"
tail -n +3 "$ROOT_CHANGELOG" >> "$TMP"  # alten Changelog anhängen
mv "$TMP" "$ROOT_CHANGELOG"