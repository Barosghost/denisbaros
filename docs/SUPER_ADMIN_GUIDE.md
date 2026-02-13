# 🔐 Guide de Configuration Super Admin

## Étapes d'Installation

### 1. Exécuter le Script de Configuration

**Option A: Via le navigateur (Recommandé)**
```
http://localhost/denis/create_super_admin.php
```

**Option B: Via la ligne de commande**
```bash
cd c:\wamp64\www\denis
php create_super_admin.php
```

### 2. Se Connecter avec le Compte Super Admin

Une fois le script exécuté, utilisez ces identifiants :

- **Nom d'utilisateur:** `superadmin`
- **Mot de passe:** `Admin@2026!`

> ⚠️ **IMPORTANT:** Changez ce mot de passe après la première connexion !

### 3. Fonctionnalités Disponibles

Le Super Admin a accès à trois nouvelles pages dans le menu :

#### 📊 **Supervision**
- Vue d'ensemble du système
- Statistiques globales (utilisateurs actifs, ventes du jour, tickets actifs)
- Top vendeurs et techniciens (30 derniers jours)
- Activités récentes de tous les utilisateurs

#### 👥 **Gestion Utilisateurs**
- Liste de tous les utilisateurs avec leurs statistiques
- Modifier les rôles (Super Admin, Admin, Vendeur, Technicien)
- Activer/Désactiver des comptes
- Voir la dernière connexion de chaque utilisateur

- Export et impression des logs

#### 🔄 **Automatisation Stock**
- Surveillance des transferts entre Stock et Service Technique
- Rapports de réintégration de stock après réparation

## Modifications Apportées

### Base de Données

✅ Table `users`:
- Nouveau rôle `super_admin` ajouté
- Colonne `is_active` pour activer/désactiver les comptes
- Colonne `last_login` pour suivre les connexions
- Colonne `permissions` (JSON) pour permissions personnalisées

✅ Table `action_logs`:
- Colonne `ip_address` pour tracer l'origine des actions
- Colonne `user_agent` pour identifier le navigateur/appareil

### Fichiers Créés

1. **`frontend/views/supervision.php`** - Tableau de bord de supervision
2. **`frontend/views/user_management.php`** - Gestion des utilisateurs
3. **`frontend/views/audit_logs.php`** - Logs d'audit détaillés
4. **`create_super_admin.php`** - Script d'installation

### Fichiers Modifiés

1. **`backend/includes/sidebar.php`** - Ajout du menu Super Admin
2. **`backend/config/functions.php`** - Amélioration de `logActivity()`
3. **`backend/auth/login.php`** - Enregistrement de `last_login`

## Sécurité

### Bonnes Pratiques

1. **Limiter les Comptes Super Admin**
   - Créez uniquement le nombre nécessaire de comptes super_admin
   - Utilisez des mots de passe très forts (min. 12 caractères)

2. **Surveiller les Activités**
   - Consultez régulièrement les logs d'audit
   - Vérifiez les adresses IP suspectes
   - Désactivez immédiatement les comptes compromis

3. **Gestion des Permissions**
   - Ne donnez le rôle super_admin qu'aux personnes de confiance
   - Utilisez le rôle `admin` pour les opérations quotidiennes
   - Le super_admin est protégé et ne peut pas être désactivé

## Dépannage

### Le menu Super Admin n'apparaît pas
- Vérifiez que vous êtes connecté avec le compte `superadmin`
- Videz le cache du navigateur (Ctrl + F5)
- Vérifiez que `$_SESSION['role']` est bien `'super_admin'`

### Erreur lors de la création du compte
- Vérifiez que WAMP est démarré
- Assurez-vous que la base de données est accessible
- Consultez les logs PHP pour plus de détails

### Les logs ne s'affichent pas
- Vérifiez que les colonnes `ip_address` et `user_agent` existent dans `action_logs`
- Réexécutez le script `create_super_admin.php`

## Support

Pour toute question ou problème, consultez :
- Les logs d'audit pour voir les erreurs
- Les logs PHP dans `c:\wamp64\logs\`
- La documentation du projet dans `DOSSIER_FINAL_SOUTENANCE.md`
