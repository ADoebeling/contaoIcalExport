# CMS Contao: iCal-Event-Export

This extension to the cms contao allows to export a event as ical-download. It is NOT a native contao-extension and it is NOT listed to the extension-manager/composer, cause it simply consists of one file/class you can easily copy&paste.
 
## How does it work?
 
 Simply link this script from your event or events-template and the browser will start to download the event as ical-file:
 `http(s)://$DOMAIN.TLD/contaoIcalExport.php?eventId=XX`
 
 The following fields are exported:
 
  * event-title
  * teaser-text
  * location
  * startDate/startTime
  * endDate/endTime
  * event-alias (as filename: $alias.ics)
   
## How to install?
 
  * Download file and store it to htdocs/httpdocs (contao-home)
  * Test it with: http://$DOMAIN.TLD/contaoIcalExport.php?eventId=XX
  * Implement iCal-Download-Button to the templates/event_full.html5 or wherever u
    need it by adding
    `<a href="/contaoIcalExport.php?eventId=<?=$this->id?>">Download as iCal-File</a>`
    
  * Implemented and tested at contao 3.3 but should also work on the most
    newer and older contao-versions
    
## Support/Questions?
  
 Please just fill [a new issue](https://github.com/ADoebeling/contaoIcalExport/issues/new)
  
## Credits
  
  **Author:** Andreas Döbeling ([GitHub](https://github.com/ADoebeling), [Xing](https://xing.doebeling.de), [Mail](mailto:ad@1601.com))
  **Copyright:** [1601.communication gmbh](https://www.1601.com)
  **License:** [CC-BY-SA](https://creativecommons.org/licenses/by-sa/3.0/)