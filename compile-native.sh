#!/usr/bin/env bash
set -euo pipefail

DIR="$(cd -P "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE="$DIR/compile.sh"
GENERATED="$DIR/.compile-native.generated.sh"

if [[ ! -f "$SOURCE" ]]; then
  echo "compile.sh not found at $SOURCE" >&2
  exit 1
fi

cp "$SOURCE" "$GENERATED"
trap 'rm -f "$GENERATED"' EXIT

python3 - "$GENERATED" <<'PY'
from pathlib import Path
import sys

path = Path(sys.argv[1])
text = path.read_text()

pathfinder_sha = "4f62992e518bd2fc8797103b5a7e3690166b074b"
math_sha = "ceee39ac0ece476accaa09e58aeb70f66276b6c7"
nbt_sha = "099ccb67c57fc0893b2c388f89f363f89f389a51"

anchor = 'EXT_PATHFINDER_VERSION="132f6c13d864bc7b48521555b9bd8cbd2706cb4c"\n'
insert = f'EXT_PATHFINDER_VERSION="{pathfinder_sha}"\nEXT_MATH_VERSION="{math_sha}"\nEXT_NBT_VERSION="{nbt_sha}"\n'
if anchor not in text:
    raise SystemExit("Unable to locate EXT_PATHFINDER_VERSION in compile.sh")
text = text.replace(anchor, insert, 1)

anchor = 'get_github_extension "pathfinder" "$EXT_PATHFINDER_VERSION" "TrixNEW" "ext-pathfinder"\n'
insert = 'get_github_extension "pathfinder" "$EXT_PATHFINDER_VERSION" "RavePvP" "ext-pathfinder"\nget_github_extension "ext_math" "$EXT_MATH_VERSION" "phpMine-MP" "ext-math"\nget_github_extension "ext_nbt" "$EXT_NBT_VERSION" "phpMine-MP" "ext-nbt"\n'
if anchor not in text:
    raise SystemExit("Unable to locate pathfinder extension download in compile.sh")
text = text.replace(anchor, insert, 1)

anchor = '--enable-pathfinder \\\n'
insert = anchor + '--enable-ext-math \\\n--enable-ext-nbt \\\n'
if anchor not in text:
    raise SystemExit("Unable to locate --enable-pathfinder in compile.sh")
text = text.replace(anchor, insert, 1)

path.write_text(text)
PY

chmod +x "$GENERATED"
exec "$GENERATED" "$@"
