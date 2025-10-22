# 🔒 FixedCoin Halving Countdown

## ✨ **Nouvelles fonctionnalités**

✅ **Countdown jusqu'au prochain halving FixedCoin**  
✅ **Stats réseau en temps réel** (difficulté, hashrate, supply)  
✅ **🆕 Calcul automatique des blocs moyens par 24h** basé sur les 100 derniers blocs  
✅ **Design personnalisé FixedCoin** (thème rouge/noir)  
✅ **Timer précis** avec ajustement basé sur la vitesse réelle du réseau

---

## 📦 **Fichiers inclus**

1. **`fixedcoin_summary.php`** - Backend API qui récupère les données
2. **`index_fixedcoin.html`** - Frontend du countdown

---

## 🚀 **Installation rapide**

### **Prérequis**
- Serveur web (Apache/Nginx) avec PHP 7.4+
- Accès à `https://explorer.fixedcoin.org`

### **Étape 1 : Upload des fichiers**

```bash
# Créer le dossier
sudo mkdir -p /var/www/fixedcoin-countdown

# Copier les fichiers
sudo cp fixedcoin_summary.php /var/www/fixedcoin-countdown/
sudo cp index_fixedcoin.html /var/www/fixedcoin-countdown/index.html

# Créer le dossier cache
sudo mkdir -p /var/www/fixedcoin-countdown/cache

# Permissions
sudo chown -R www-data:www-data /var/www/fixedcoin-countdown
sudo chmod 755 /var/www/fixedcoin-countdown
sudo chmod 775 /var/www/fixedcoin-countdown/cache
```

### **Étape 2 : Configuration Apache**

Créer `/etc/apache2/sites-available/fixedcoin-countdown.conf` :

```apache
<VirtualHost *:80>
    ServerName halving.fixedcoin.org
    DocumentRoot /var/www/fixedcoin-countdown

    <Directory /var/www/fixedcoin-countdown>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/fixedcoin-countdown-error.log
    CustomLog ${APACHE_LOG_DIR}/fixedcoin-countdown-access.log combined
</VirtualHost>
```

Activer le site :
```bash
sudo a2ensite fixedcoin-countdown
sudo systemctl reload apache2
```

### **Étape 3 : Configuration Nginx (alternative)**

Ajouter dans votre configuration Nginx :

```nginx
server {
    listen 80;
    server_name halving.fixedcoin.org;
    root /var/www/fixedcoin-countdown;
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

Recharger Nginx :
```bash
sudo systemctl reload nginx
```

---

## 🔧 **Comment ça marche**

### **Calcul des blocs moyens par 24h**

Le backend PHP :

1. **Récupère le bloc actuel** via `/api/getblockcount`
2. **Récupère les timestamps** :
   - Bloc actuel (ex: 392)
   - Bloc -100 (ex: 292)
3. **Calcule le temps moyen** :
   ```
   Temps total = Timestamp(bloc 392) - Timestamp(bloc 292)
   Temps moyen par bloc = Temps total / 100 blocs
   ```
4. **Calcule les blocs par 24h** :
   ```
   Blocs par 24h = 86400 secondes / Temps moyen par bloc
   ```

### **Exemple concret**

```
Bloc actuel : 392 (timestamp: 1729600000)
Bloc 292    : 292 (timestamp: 1729540000)

Temps total = 60000 secondes
Temps moyen = 60000 / 100 = 600 sec/bloc (10 minutes)
Blocs/24h   = 86400 / 600 = 144 blocs par jour
```

Si le réseau mine plus vite (8 min/bloc) :
```
Blocs/24h = 86400 / 480 = 180 blocs par jour ⚡
```

---

## 📊 **Caractéristiques FixedCoin**

### **Supply Schedule**
- **Total Supply** : 10,000 FIX
- **Genesis Block** : 1 FIX (bloc 0)
- **Premine** : 1,600 FIX (bloc 1)
- **Block Time** : 10 minutes
- **Halving Interval** : 4,200 blocs

### **Halving Schedule**
| Bloc | Récompense |
|------|-----------|
| 0 | 1 FIX (Genesis) |
| 1 | 1,600 FIX (Premine) |
| 2 - 4,199 | 1 FIX |
| 4,200 - 8,399 | 0.5 FIX |
| 8,400 - 12,599 | 0.25 FIX |
| 12,600 - 16,799 | 0.125 FIX |
| ... | (continue) |
| 113,400+ | 0 FIX |

---

## 🎨 **Personnalisation**

### **Changer les couleurs**

Dans `index_fixedcoin.html`, modifier le CSS :

```css
/* Couleur principale (rouge) */
background: linear-gradient(135deg,#1a1a1a 0%,#2d0a0a 50%,#1a1a1a 100%);

/* Bordures et accents */
border: 2px solid rgba(220,20,60,.3);

/* Texte highlight */
color: #dc143c;
```

### **Changer le logo**

Remplacer l'URL dans le HTML :
```html
<img class="logo" src="VOTRE_URL_LOGO.png" alt="FIX"/>
```

### **Changer l'intervalle de rafraîchissement**

Dans le JavaScript :
```javascript
const REFRESH_DEFAULT = 10000; // 10 secondes
const REFRESH_NEAR = 5000;     // 5 secondes quand proche du halving
```

---

## 🐛 **Dépannage**

### **Les données ne s'affichent pas**

```bash
# Vérifier les logs PHP
tail -f /var/log/apache2/error.log
# ou
tail -f /var/log/nginx/error.log

# Tester l'API manuellement
curl http://votre-domaine.com/fixedcoin_summary.php

# Vider le cache
rm -f /var/www/fixedcoin-countdown/cache/state.json
```

### **"Avg Blocks per 24h" affiche "-"**

Le calcul nécessite **au moins 100 blocs** dans la blockchain.

Actuellement : **392 blocs** ✅ (suffisant)

Si le réseau a moins de 100 blocs, le système affiche la valeur théorique (144 blocs/jour).

### **Erreur "Connection error"**

Vérifier que l'explorer est accessible :
```bash
curl https://explorer.fixedcoin.org/api/getblockcount
```

---

## 📈 **Statistiques affichées**

| Stat | Source | Description |
|------|--------|-------------|
| **Current Block** | `/api/getblockcount` | Hauteur actuelle de la blockchain |
| **Next Halving Block** | Calculé | Prochain bloc de halving (ex: 4200) |
| **Current Reward** | Calculé | Récompense actuelle par bloc |
| **Next Reward** | Calculé | Récompense après le halving |
| **Network Difficulty** | `/api/getdifficulty` | Difficulté actuelle |
| **Circulating Supply** | `/ext/getmoneysupply` | FIX en circulation / 10,000 |
| **Network Hashrate** | `/api/getnetworkhashps` | Puissance de calcul du réseau |
| **🆕 Avg Blocks per 24h** | **Calculé** | Blocs moyens sur 24h (basé sur 100 derniers) |

---

## 🔗 **Liens utiles**

- **Explorer** : https://explorer.fixedcoin.org
- **Website** : https://fixedcoin.org
- **GitHub** : https://github.com/Fixed-Blockchain/fixedcoin
- **Releases** : https://github.com/Fixed-Blockchain/fixedcoin/releases

---

## ✅ **Checklist post-installation**

- [ ] Fichiers copiés dans `/var/www/fixedcoin-countdown/`
- [ ] Dossier `cache/` créé avec permissions 775
- [ ] Site accessible dans le navigateur
- [ ] Données s'affichent correctement
- [ ] Timer compte à rebours fonctionne
- [ ] **"Avg Blocks per 24h" affiche une valeur** (ex: 144.2)
- [ ] Aucune erreur dans la console navigateur (F12)
- [ ] Aucune erreur dans les logs serveur

---

## 🎯 **Exemple de réponse API**

```json
{
  "serverTime": 1729600000000,
  "as_of_ms": 1729600000000,
  "block": 392,
  "difficulty": 3815361741.333008,
  "supply": 1990,
  "hashrate": {
    "value": 1.234,
    "unit": "TH/s",
    "human": "1.234 TH/s"
  },
  "currentReward": 1,
  "nextReward": 0.5,
  "nextHalvingBlock": 4200,
  "blocksRemaining": 3808,
  "progressPct": 8.5,
  "targetHalvingTs": 1731876000000,
  "avgBlocksPer24h": 144.2,
  "actualBlockTime": 598.6
}
```

---

**🔒 Everything is Fixed. Forever.**

*Besoin d'aide ? Ouvrez une issue sur GitHub ou contactez la communauté FixedCoin !*
