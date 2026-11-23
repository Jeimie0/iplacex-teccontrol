#!/bin/sh
set -eu

cert_dir=/var/lib/teccontrol/tls
umask 077
mkdir -p "$cert_dir"

if [ ! -s "$cert_dir/ca.crt" ] || [ ! -s "$cert_dir/localhost.crt" ] || [ ! -s "$cert_dir/localhost.key" ]; then
    work_dir=$(mktemp -d "$cert_dir/.cert.XXXXXX")
    trap 'rm -f -- "$work_dir"/*; rmdir -- "$work_dir"' EXIT

    openssl req -x509 -newkey rsa:3072 -noenc -days 730 -sha256 \
        -subj '/CN=TecControl Local CA' \
        -addext 'basicConstraints=critical,CA:TRUE,pathlen:0' \
        -addext 'keyUsage=critical,keyCertSign,cRLSign' \
        -keyout "$work_dir/ca.key" -out "$work_dir/ca.crt"

    openssl req -new -newkey rsa:2048 -noenc -sha256 -subj '/CN=localhost' \
        -keyout "$work_dir/localhost.key" -out "$work_dir/localhost.csr"

    cat > "$work_dir/localhost.ext" <<'EOF'
basicConstraints=critical,CA:FALSE
keyUsage=critical,digitalSignature,keyEncipherment
extendedKeyUsage=serverAuth
subjectAltName=DNS:localhost,IP:127.0.0.1
EOF

    openssl x509 -req -in "$work_dir/localhost.csr" \
        -CA "$work_dir/ca.crt" -CAkey "$work_dir/ca.key" -CAcreateserial \
        -days 365 -sha256 -extfile "$work_dir/localhost.ext" -out "$work_dir/localhost.crt"

    mv -- "$work_dir/localhost.key" "$work_dir/localhost.crt" "$work_dir/ca.crt" "$cert_dir/"
    rm -f -- "$work_dir"/*
    rmdir -- "$work_dir"
    trap - EXIT
fi

umask 022
exec docker-php-entrypoint "$@"
