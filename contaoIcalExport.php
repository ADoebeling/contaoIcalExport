<?php

namespace contao;

/**
 * Run this file as script
 * (procedural at the end will executed)
 */
if (!defined(runContaoIcalExportAsScript)) define('runContaoIcalExportAsScript', true);

/**
 * ICAL-Event-Export for Contao 3.3
 * Sends the given event-id (contaoIcalExport.php?eventId=XX) als iCal-File to the browser
 * (other contao-versions may be compatible too)
 *
 * Installation
 * - Download file and store it to htdocs/httpdocs (contao-home)
 * - Test it with: http://$DOMAIN.TLD/contaoIcalExport.php?eventId=XX
 * - Implement iCal-Download-Button to the templates/event_full.html5 or wherever u
 *   need it by adding
 *   <a href="/contaoIcalExport.php?eventId=<?=$this->id?>">Download as iCal-File</a>
 *
 * Thanks to
 * - PhpStorm for the great ide
 * - Jake Bellacera for his draft: PHPtoICS.php
 *   https://gist.github.com/jakebellacera/635416
 * - Hugo Wetterberg for his function ical_split
 *   https://gist.github.com/hugowetterberg/81747
 * - Steven N. Severinghaus for his iCalendar Validator
 *   http://severinghaus.org/projects/icv/
 * - iCalendar.org for its iCalendar Validator
 *   http://icalendar.org/component/com_icalvalidator/
 *
 * @author Andreas Doebeling <ad@1601.com>
 * @copyright 1601.production siegler&thuemmler ohg
 * @license cc-by-sa https://creativecommons.org/licenses/by-sa/3.0/
 *
 * @link https://github.com/ADoebeling/contaoIcalExport
 * @link https://xing.doebeling.de
 *
 * @version 0.1.160412_1ad
 */
class icalExport
{
    /**
     * @var integer the id of the event
     */
    protected $eventId = 0;

    /**
     * @var string text title of the event
     */
    protected $summary = '';

    /**
     * @var integer the starting date (in seconds since unix epoch)
     */
    protected $dateStart = 0;

    /**
     * @var integer  the ending date (in seconds since unix epoch)
     */
    protected $dateEnd = 0;

    /**
     * @var string the event's address
     */
    protected $address = '';

    /**
     * @var string the URL of the event (add http://)
     */
    protected $uri = '';

    /**
     * @var string text description of the event
     */
    protected $description = '';

    /**
     * @var string the name of this file for saving (e.g. my-event-name.ics)
     */
    protected $filename = '';

    /**
     * @var \mysqli
     */
    protected $mysqli;

    /**
     * icalExport constructor.
     *
     * @param $dbHost
     * @param $dbUser
     * @param $dbPass
     * @param $dbDatabase
     */
    public function __construct($dbHost, $dbUser, $dbPass, $dbDatabase)
    {
        $this->mysqli = new \mysqli($dbHost, $dbUser, $dbPass, $dbDatabase);
        if ($this->mysqli->errno)
        {
            printf("Connect failed: %s\n", mysqli_connect_error());
            die();
        }
    }

    /**
     * Get single event
     *
     * @param integer $eventId
     * @return $this
     * @throws \Exception
     */
    public function get($eventId)
    {
        $eventId = intval($eventId);
        $sql = "
          SELECT
              id,
              title as summery,
              concat(alias, '.ics') as filename,
              location as address,
              teaser as description,

              -- dateStart
              if(addTime = 1,
                 -- Time is given
                 DATE_FORMAT(from_unixtime(startTime), 'DTSTART;TZID=Europe/Berlin:%Y%m%dT%H%i%s'),

                 -- Only date is given
                 DATE_FORMAT(from_unixtime(startDate), 'DTSTART;VALUE=DATE:%Y%m%d')
              ) as dateStart,

              -- dateEnd
              if(addTime = 1,

                 -- Time is given
                 if(endTime = 0 || endTime <= startTime || DATE_FORMAT(from_unixtime(endTime), '%H%i') = '2323',

                   -- Time is given but 0, wrong OR 23:23 (what we use as identifier for unlimited events)
                   DATE_FORMAT(from_unixtime(startTime + 2 * 60 * 60), 'DTEND;TZID=Europe/Berlin:%Y%m%dT%H%i%s'),

                   -- Time is given and correct
                   DATE_FORMAT(from_unixtime(endTime), 'DTEND;TZID=Europe/Berlin:%Y%m%dT%H%i%s')
                 ),

                -- Only date is given
                 if(endDate != 0 && endDate > startDate,

                    -- EndDate is correkt
                    DATE_FORMAT(from_unixtime(endDate + 24 * 60 * 60), 'DTEND;VALUE=DATE:%Y%m%d'),

                   -- EndDate is not correkt, take the startDate
                   DATE_FORMAT(from_unixtime(startDate + 24 * 60 * 60), 'DTEND;VALUE=DATE:%Y%m%d')

                 )
              ) as dateEnd

            FROM
              tl_calendar_events

            WHERE
              id = $eventId AND
              published = 1

            LIMIT 1";

        $query = $this->mysqli->query($sql);

        if ($query->num_rows !== 1)
        {
            throw new \Exception("EventId not found\n$sql");
        }
        else
        {
            $result = $query->fetch_assoc();
            $this->dateStart = $result['dateStart'];
            $this->dateEnd = $result['dateEnd'];
            $this->address = $result['address'];
            $this->description = strip_tags($result['description']);
            $this->filename = strip_tags($result['filename']);
            $this->summary = strip_tags(html_entity_decode($result['summery']));
            $this->uri = $_SERVER[HTTP_REFERER];
            return $this;
        }
    }

    /**
     * Send ical-file as download
     */
    public function sendDownload()
    {
        $ics ="BEGIN:VCALENDAR\r\n";
        $ics .="VERSION:2.0\r\n";
        $ics .="PRODID:-//hacksw/handcal//NONSGML v1.0//EN\r\n";
        $ics .="CALSCALE:GREGORIAN\r\n";

        // Timezone-settings borrowed from
        // http://pcal.gedaechtniskirche.com/termine/index.php?cal=kwg-probenplan&ics
        $ics .= "BEGIN:VTIMEZONE\r\n";
        $ics .= "TZID:Europe/Berlin\r\n";
        $ics .= "BEGIN:DAYLIGHT\r\n";
        $ics .= "TZOFFSETFROM:+0100\r\n";
        $ics .= "DTSTART:19810329T020000\r\n";
        $ics .= "RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU\r\n";
        $ics .= "TZNAME:MESZ\r\n";
        $ics .= "END:DAYLIGHT\r\n";
        $ics .= "BEGIN:STANDARD\r\n";
        $ics .= "TZOFFSETFROM:+0200\r\n";
        $ics .= "DTSTART:19961027T030000\r\n";
        $ics .= "RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU\r\n";
        $ics .= "TZNAME:MEZ\r\n";
        $ics .= "END:STANDARD\r\n";
        $ics .= "END:VTIMEZONE\r\n";

        $ics .="BEGIN:VEVENT\r\n";
        $ics .=$this->dateEnd."\r\n";
        $ics .="UID:".$this->eventId."\r\n";
        $ics .="DTSTAMP:".$this->dateStart."\r\n";
        $ics .="LOCATION:".$this->address."\r\n";
        $ics .= 'DESCRIPTION:'.$this->getIcalSplit('DESCRIPTION', $this->description)."\r\n";
        $ics .="URL;VALUE=URI:".$this->uri."\r\n";
        $ics .= 'SUMMARY:'.$this->getIcalSplit('SUMMARY', $this->summary)."\r\n";
        $ics .=$this->dateStart."\r\n";
        $ics .="END:VEVENT\r\n";
        $ics .="END:VCALENDAR\r\n";

        header('Content-type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $this->filename);
        echo utf8_encode($ics);
    }

    /**
     * @author Hugo Wetterberg <hugo@wetterberg.nu>
     * @link https://gist.github.com/hugowetterberg/81747
     *
     * @param $preamble
     * @param $value
     * @return string
     */
    public function getIcalSplit($preamble, $value) {
        return $value; // disabled
        $value = trim($value);
        $value = strip_tags($value);
        $value = preg_replace('/\n+/', ' ', $value);
        $value = preg_replace('/\s{2,}/', ' ', $value);
        $preamble_len = strlen($preamble);
        $lines = array();
        while (strlen($value)>(75-$preamble_len)) {
            $space = (75-$preamble_len);
            $mbcc = $space;
            while ($mbcc) {
                $line = mb_substr($value, 0, $mbcc);
                $oct = strlen($line);
                if ($oct > $space) {
                    $mbcc -= $oct-$space;
                }
                else {
                    $lines[] = $line;
                    $preamble_len = 1; // Still take the tab into account
                    $value = mb_substr($value, $mbcc);
                    break;
                }
            }
        }
        if (!empty($value)) {
            $lines[] = $value;
        }
        return join($lines, "\n\t");
    }
}

/******************************************************************************
 ******************************************************************************/

if (runContaoIcalExportAsScript && isset($_GET['eventId']) && intval($_GET['eventId']))
{
    require_once 'system/config/localconfig.php';

    $cie = new icalExport(
        $GLOBALS['TL_CONFIG']['dbHost'],
        $GLOBALS['TL_CONFIG']['dbUser'],
        $GLOBALS['TL_CONFIG']['dbPass'],
        $GLOBALS['TL_CONFIG']['dbDatabase']
    );
    $cie->get($_GET['eventId'])->sendDownload();
}
