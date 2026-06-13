# Apply Local Host Changes

Run these commands from the directory that contains this `data` folder.

## Hosts

```sh
sudo sh -c 'tmp=$(mktemp); sed "/# wp-local start/,/# wp-local end/d" /etc/hosts > "$tmp"; printf "\n# wp-local start\n127.0.0.1 {{hostnames with space separated}}\n::1 {{hostnames with space separated}}\n# wp-local end\n" >> "$tmp"; cat "$tmp" > /etc/hosts; rm "$tmp"'
```

## Nginx

```sh
sudo sh -c 'mkdir -p /etc/nginx/sites-available; printf "include {{nginx include path}};\n" > /etc/nginx/sites-available/local-wildcard.conf'
```

## Restart

```sh
sudo nginx -t && sudo systemctl reload nginx
docker compose restart
```
