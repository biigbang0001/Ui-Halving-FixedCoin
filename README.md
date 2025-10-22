# FixedCoin Halving Countdown

Site web : **https://halving.fixedcoin.org**

## Installation

### 1. Copier les fichiers

```bash
sudo mkdir -p /var/www/halving-fixedcoin/cache
sudo cp index.html /var/www/halving-fixedcoin/
sudo cp fixedcoin_summary.php /var/www/halving-fixedcoin/
sudo chown -R www-data:www-data /var/www/halving-fixedcoin
sudo chmod 775 /var/www/halving-fixedcoin/cache
```

### 2. Configuration Nginx

Créer `/etc/nginx/sites-available/halving-fixedcoin` :

```nginx
server {
    listen 80;
    server_name halving.fixedcoin.org;
    root /var/www/halving-fixedcoin;
    index index.html;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    }
}
```

Activer :

```bash
sudo ln -s /etc/nginx/sites-available/halving-fixedcoin /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 3. SSL

```bash
sudo certbot --nginx -d halving.fixedcoin.org
```

## Fonctionnalités

- Countdown jusqu'au prochain halving
- Stats réseau en temps réel
- **Nouveau :** Calcul automatique des blocs moyens par 24h (basé sur les 100 derniers blocs)
- Design rouge/noir thème FixedCoin

## Schedule de halving

| Bloc | Récompense |
|------|-----------|
| 0 | 1 FIX (Genesis) |
| 1 | 1,600 FIX (Premine) |
| 2-4,199 | 1 FIX |
| 4,200-8,399 | 0.5 FIX |
| 8,400+ | (continue halving) |

Total Supply : 10,000 FIX

## Dépannage

```bash
# Vider le cache
sudo rm -f /var/www/halving-fixedcoin/cache/state.json

# Voir les logs
sudo tail -f /var/log/nginx/error.log
```

## Liens

- Explorer : https://explorer.fixedcoin.org
- Website : https://fixedcoin.org
- GitHub : https://github.com/Fixed-Blockchain/fixedcoin
