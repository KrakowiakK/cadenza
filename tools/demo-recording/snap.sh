#!/usr/bin/env bash
# snap.sh save|restore NAME — the state of the recording site (both databases + uploads), so a beat can be re-recorded.
set -euo pipefail
CZ="${CZ:?}"; D="$(cd "$(dirname "$0")" && pwd)/snapshots/$2"
case "$1" in
  save) rm -rf "$D"; mkdir -p "$D/uploads"
        cp "$CZ"/demo-app/var/data_dev.db* "$D/" 2>/dev/null; mkdir -p "$D/h"; cp "$CZ"/harness/var/data_dev.db* "$D/h/"
        cp -R "$CZ"/harness/var/uploads/. "$D/uploads/" 2>/dev/null || true; echo "saved $2" ;;
  restore) [ -d "$D" ] || { echo "no snapshot $2"; exit 1; }
        rm -f "$CZ"/demo-app/var/data_dev.db* "$CZ"/harness/var/data_dev.db*
        cp "$D"/data_dev.db* "$CZ/demo-app/var/"; cp "$D"/h/data_dev.db* "$CZ/harness/var/"
        rm -rf "$CZ"/harness/var/uploads; mkdir -p "$CZ"/harness/var/uploads; cp -R "$D/uploads/." "$CZ/harness/var/uploads/" 2>/dev/null || true
        echo "restored $2" ;;
esac
