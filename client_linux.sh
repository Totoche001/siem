#!/bin/bash

# Configuration du script
SERVER="192.168.1.100"    # IP ou hostname du serveur
CLIENT="client_$(hostname)"    # Nom du client basé sur le hostname
TMPDIR="/tmp/logs_monitor"    # Dossier temporaire pour stocker les logs
mkdir -p "$TMPDIR"    # Création du dossier temporaire si inexistant

# Génération du timestamp pour le nom de fichier
NOW=$(date +%Y-%m-%d_%H-%M-%S)
LOGFILE="$TMPDIR/syslog_$NOW.log"    # Nom du fichier de log avec timestamp

# Extraction des logs système
# Essaie d'abord journalctl, puis /var/log/syslog en fallback
if command -v journalctl &> /dev/null; then
    journalctl -n 1000 > "$LOGFILE"    # Récupère les 1000 dernières lignes de journalctl
elif [ -f "/var/log/syslog" ]; then
    tail -n 1000 /var/log/syslog > "$LOGFILE"    # Récupère les 1000 dernières lignes de syslog
else
    echo "Pas de source de logs dispo"    # Aucune source de logs trouvée
    exit 1
fi

# Vérification que le fichier de logs n'est pas vide
if [ ! -s "$LOGFILE" ]; then
    echo "Fichier de logs vide, annulation."
    exit 1
fi

echo "Envoi vers $SERVER (client: $CLIENT, fichier: $LOGFILE)..."

# Envoi des logs au serveur via HTTP POST
curl -X POST -F "client=$CLIENT" -F "type=system" -F "file=@$LOGFILE" "http://$SERVER/receive_logs.php"

# Nettoyage du fichier temporaire
rm -f "$LOGFILE"