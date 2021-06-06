# DHL_entwicklerportal_api_2
Sendungsverfolgung - shipment tracking (d-get-piece, d-get-piece-events, d-get-piece-detail, d-get-signature), DHL API für Geschäftskunden / for commercial users

Der hier vorgestellte Code ist ein PHP Wrapper für die API aus dem DHL Entwicklerportal und dient der Sendungsverfolgung von DHL Produkten mit Sendungsnummer (Paket / Warenpost National). 

Er unsterstützt alle 4 Modi für Geschäftskunden:

	-d-get-piece: Abfrage des aktuellen Sendungsstatus mit erweiterten Informationen
	-d-get-piece-events: Abfrage des Sendungsverlaufs mit allen Einzelereignissen zur einer Sendung
	-d-get-piece-detail: Kombinierter Aufruf von Sendungsstatus und Laufweg
	-d-get-signature: Abfrage der Unterschrift des Empfängers bzw. Ersatzempfängers (Zustellnachweis / POD)


-------------------------------------------------------------------
# STARTUP
-------------------------------------------------------------------
In den Zeilen 45-52 müssen die Variablen entsprechend der persönlichen Bedürfnisse angepasst werden.
Danach einfach die Datei über ihren Namen im Browser aufrufen.

-------------------------------------------------------------------
# Integration in ein eigenes Projekt:
-------------------------------------------------------------------
Im Sandbox-Betrieb müssen Sie bestimmte von DHL zu Testzwecken vorgegebene Sendungsnummern verwenden.
Eigene Sendungsnummern sind nicht erlaubt.

Der Wrapper analysiert die von Ihnen übergebenen Sendungsnummern und speichert alle ermittelten Ergebnisse in der Variable $status, die in Zeile 186 mit print_r($status); ausgegeben wird. Im eigenen Projekt verzichten Sie sicher auf diese Bildschirmausgabe, die hier nur zu Demo-Zwecken hinsichtlich des Aufbaus der Ergebnisstruktur verwendet wird. Die einzelnen Ergebnisse können direkt über die Sendungsnummer $status[0034............] angesprochen werden, oder auch über eine foreach-Schleife. Zu diesem Zweck ist die Sendungnummer auch nochmals innerhalb des $status['details']  - Blocks enthalten.

Im LIVE-Betrieb benötigen Sie in Ihrem Code zunächst ein leeres Array mit Namen $allShipmentIds. Sie definieren es wie folgt: $allShipmentIds = array();
Wenn Sie anschließend die Funktion loadShipmentNumber("0034...........") verwenden, werden die von Ihnen hier (einzeln) übergebenen Sendungsnummern in diesem Array hinterlegt. Natürlich können Sie stattdessen auch einfach ein eigenes Nummern-Array mit diesem Namen anlegen und füllen.

Falls der operation mode 'd-get-signature' verwendet wird, liefert DHL anstatt Zustelldaten die Unterschriften des Empfängers.
Der Wrapper legt hierfür ein Unterverzeichnis an und speichert dort die Unterschriftsdateien ab.
Beachten Sie aus Datenschutzgründen, dass die Dateien vom Wrapper nicht wieder gelöscht werden.
Sie sollten die erzeugten Dateien regelmäßig nach Gebrauch wieder löschen.

-------------------------------------------------------------------
# ENDE
-------------------------------------------------------------------
  
