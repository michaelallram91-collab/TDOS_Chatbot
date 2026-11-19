<?php
/**
 * Weiterleitung zur Ausdruck-Station.
 *
 * Die frühere, offene Druckansicht wurde durch eine geschützte
 * Mitarbeiter-Oberfläche (print-station.php) ersetzt. Der Zugriff auf die
 * Fotos erfolgt ausschließlich über die Ausdruck-Station mit User-ID + PIN.
 */

header('Location: print-station.php');
exit;
