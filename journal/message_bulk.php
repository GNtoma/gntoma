<?php
/**
 * PROJET : GNTOMA
 * FICHIER : journal/message_bulk.php
 * DESCRIPTION : Redirige vers la page unifiée de composition de message en mode "bulk"
 * (l'ancienne page séparée a été fusionnée avec message_send.php)
 */
session_start();
header("Location: message_send.php?mode=bulk", true, 301);
exit;
