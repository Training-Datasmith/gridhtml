#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
TOOLS_DIR="${TOOLS_DIR:-/tmp/gridhtml-tools}"
PHPUNIT_48_SHA="ab8bc3017a64cf75a2112f5e089a1d5b0c4cc2116556d60c1af5e3f584e85c5e"
PHPUNIT_96_SHA="f195cd37de1bd14b4b60aa90af5bea95e8506d828c0dbbcf8dca03d78a38e79f"
PHP54_DIGEST="sha256:d9eaac6344eb9399f053ef6b538a237101d686d866fb484ad4834bfada6afbd9"
PHP56_DIGEST="sha256:6ce95208609dc66df163ab936c970b3b34cd901b85c747102c5999f08ade9143"
PHP81_DIGEST="sha256:d1ec09a1b2b0fadbdd0f0a29aa919d0a1c4d1d70b0ad879edd95981ebda65587"
PHP54_IMAGE="gridhtml-php54-cli"
RANDOM_SEED="20261008"

mkdir -p "$TOOLS_DIR"
if [[ ! -f "$TOOLS_DIR/phpunit-4.8.36.phar" ]]; then
  curl -fsSL -o "$TOOLS_DIR/phpunit-4.8.36.phar" https://phar.phpunit.de/phpunit-4.8.36.phar
fi
if [[ ! -f "$TOOLS_DIR/phpunit-9.6.23.phar" ]]; then
  curl -fsSL -o "$TOOLS_DIR/phpunit-9.6.23.phar" https://phar.phpunit.de/phpunit-9.6.23.phar
fi
echo "${PHPUNIT_48_SHA}  ${TOOLS_DIR}/phpunit-4.8.36.phar" | sha256sum -c -
echo "${PHPUNIT_96_SHA}  ${TOOLS_DIR}/phpunit-9.6.23.phar" | sha256sum -c -

verify_extensions() {
  local image="$1"
  docker run --rm "$image" php -r '
$required = array("dom", "json", "libxml", "mbstring", "tokenizer", "xml", "xmlwriter");
$missing = array();
foreach ($required as $extension) {
    if (!extension_loaded($extension)) {
        $missing[] = $extension;
    }
}
if ($missing) {
    fwrite(STDERR, "Missing extensions: " . implode(", ", $missing) . PHP_EOL);
    exit(1);
}
echo PHP_VERSION, PHP_EOL;
'
}

run_phpunit() {
  local image="$1"
  local phar="$2"
  shift 2
  docker run --rm -v "$REPO_ROOT":/app -w /app -v "$TOOLS_DIR":/tools "$image" \
    php -d error_reporting=-1 /tools/"$phar" --configuration phpunit.xml.dist "$@"
}

select_floor_image() {
  if docker run --rm "php:5.4-cli@${PHP54_DIGEST}" php -r 'echo PHP_VERSION;' >/tmp/gridhtml-php54-version.txt 2>/tmp/gridhtml-php54-version.err; then
    FLOOR_VERSION="$(cat /tmp/gridhtml-php54-version.txt)"
    if [[ "$FLOOR_VERSION" != "5.4.45" ]]; then
      echo "Unexpected PHP floor version: ${FLOOR_VERSION}" >&2
      exit 1
    fi
    if verify_extensions "php:5.4-cli@${PHP54_DIGEST}" >/tmp/gridhtml-php54-version.txt; then
      echo "php:5.4-cli@${PHP54_DIGEST}"
      return 0
    fi
    if docker image inspect "$PHP54_IMAGE" >/dev/null 2>&1 && verify_extensions "$PHP54_IMAGE" >/tmp/gridhtml-php54-version.txt; then
      echo "$PHP54_IMAGE"
      return 0
    fi
    docker build -t "$PHP54_IMAGE" -f "$REPO_ROOT/tests/docker/php54-cli.Dockerfile" "$REPO_ROOT/tests/docker"
    verify_extensions "$PHP54_IMAGE" >/tmp/gridhtml-php54-version.txt
    echo "$PHP54_IMAGE"
    return 0
  fi

  echo "PHP 5.4 digest image unavailable on this Docker engine; using PHP 5.6.40 fallback" >&2
  cat /tmp/gridhtml-php54-version.err >&2 || true
  FLOOR_IMAGE="php:5.6-cli@${PHP56_DIGEST}"
  FLOOR_VERSION="$(verify_extensions "$FLOOR_IMAGE")"
  if [[ "$FLOOR_VERSION" != "5.6.40" ]]; then
    echo "Unexpected PHP fallback version: ${FLOOR_VERSION}" >&2
    exit 1
  fi
  echo "$FLOOR_IMAGE"
}

FLOOR_IMAGE="$(select_floor_image)"
FLOOR_VERSION="$(docker run --rm "$FLOOR_IMAGE" php -r 'echo PHP_VERSION;')"

echo "Running floor suite on ${FLOOR_IMAGE} (${FLOOR_VERSION})"
run_phpunit "$FLOOR_IMAGE" phpunit-4.8.36.phar

echo "Running PHP 8.1 default-order suite"
run_phpunit "php:8.1.32-cli@${PHP81_DIGEST}" phpunit-9.6.23.phar

echo "Running PHP 8.1 random-order suite (seed ${RANDOM_SEED})"
run_phpunit "php:8.1.32-cli@${PHP81_DIGEST}" phpunit-9.6.23.phar --order-by=random --random-order-seed="${RANDOM_SEED}"
