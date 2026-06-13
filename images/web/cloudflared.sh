#!/bin/sh
set -eu

log_dir="/data/cloudflared"
urls_file="/data/cloudflared-urls.txt"
cloudflared_token="${CLOUDFLARED_API_KEY:-}"

normalize_site() {
	printf '%s' "$1" | tr -d '[:space:]' | tr '[:upper:]' '[:lower:]'
}

csv_value_at() {
	csv="$1"
	index="$2"

	awk -v csv="$csv" -v index="$index" 'BEGIN {
		n = split(csv, values, ",")
		if (index <= n) {
			print values[index]
		}
	}'
}

site_value_at() {
	csv="$1"
	index="$2"

	normalize_site "$(csv_value_at "$csv" "$index")"
}

cloudflared_value_at() {
	csv="$1"
	index="$2"

	printf '%s' "$(csv_value_at "$csv" "$index" | sed 's/^[[:space:]]*//; s/[[:space:]]*$//')"
}

record_url() {
	site="$1"
	target="$2"
	log_file="$3"

	if [ "$target" != '-' ]; then
		printf '%s -> https://%s\n' "$site" "$target" >> "$urls_file"
		return 0
	fi

	for _ in $(seq 1 45); do
		url=$(sed -n 's/.*\(https:\/\/[-a-zA-Z0-9.]*\.trycloudflare\.com\).*/\1/p' "$log_file" 2>/dev/null | tail -n 1)
		if [ -n "$url" ]; then
			printf '%s -> %s\n' "$site" "$url" >> "$urls_file"
			return 0
		fi
		sleep 1
	done

	printf '%s -> pending; see %s\n' "$site" "$log_file" >> "$urls_file"
}

start_cloudflared() {
	[ -n "${CLOUDFLARED_SITES:-}" ] || return 0

	if ! command -v cloudflared >/dev/null 2>&1; then
		printf 'cloudflared is not installed; skipping tunnels.\n' >&2
		return 0
	fi

	mkdir -p "$log_dir"
	: > "$urls_file"

	index=1
	while :; do
		site=$(site_value_at "${SITES:-}" "$index")
		target=$(cloudflared_value_at "${CLOUDFLARED_SITES:-}" "$index")

		[ -n "$site" ] || break

		if [ -n "$target" ]; then
			log_file="${log_dir}/${site}.log"
			: > "$log_file"

			if [ -n "$cloudflared_token" ]; then
				export TUNNEL_TOKEN="$cloudflared_token"
				export CLOUDFLARE_API_TOKEN="$cloudflared_token"
			fi

			if [ "$target" = '-' ]; then
				cloudflared tunnel --no-autoupdate --url http://127.0.0.1:80 --http-host-header "$site" > "$log_file" 2>&1 &
			else
				cloudflared tunnel --no-autoupdate --url http://127.0.0.1:80 --http-host-header "$site" --hostname "$target" > "$log_file" 2>&1 &
			fi

			record_url "$site" "$target" "$log_file" &
		fi

		index=$((index + 1))
	done
}

start_cloudflared
