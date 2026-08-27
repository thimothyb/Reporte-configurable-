<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Exporter for legacy itop data (PDF and Excel).
 *
 * Produces audit-ready exports with legacy watermark, scenario badge,
 * and all required metadata for FUNDAE/SEPE compliance.
 *
 * @package    block_configurable_reports
 * @subpackage legacy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_configurable_reports\legacy;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/excellib.class.php');

/**
 * Exports legacy itop data to downloadable formats.
 */
class exporter {

    /** @var reader The legacy reader instance. */
    private $reader;

    /** @var int The course ID. */
    private $courseid;

    /** @var \stdClass The scenario info object. */
    private $scenarioinfo;

    /**
     * Constructor.
     *
     * @param int $courseid The course ID to export data for.
     */
    public function __construct(int $courseid) {
        $this->courseid = $courseid;
        $this->reader = new reader($courseid);
        $this->scenarioinfo = detector::get_scenario_info($courseid);
    }

    /**
     * Export dedication times to Excel.
     *
     * @param string $filename The filename without extension.
     */
    public function export_dedication_excel(string $filename = 'legacy_dedication'): void {
        $data = $this->reader->get_dedication_times();
        $summary = $this->reader->get_executive_summary();

        $filename = clean_filename($filename . '_' . $this->courseid);
        $workbook = new \MoodleExcelWorkbook('-');
        $workbook->send($filename . '.xlsx');

        // Metadata sheet.
        $metasheet = $workbook->add_worksheet(
            get_string('legacy_export_metadata', 'block_configurable_reports')
        );
        $this->write_metadata_sheet($metasheet, $summary);

        // Data sheet.
        $datasheet = $workbook->add_worksheet(
            get_string('legacy_export_dedication', 'block_configurable_reports')
        );

        // Header format.
        $headerformat = $workbook->add_format([
            'bold' => 1,
            'bg_color' => '#0ABCC9',
            'color' => '#FFFFFF',
        ]);

        // Legacy watermark format.
        $legacyformat = $workbook->add_format([
            'italic' => 1,
            'color' => '#999999',
        ]);

        // Headers.
        $headers = [
            get_string('legacy_col_userid', 'block_configurable_reports'),
            get_string('legacy_col_firstname', 'block_configurable_reports'),
            get_string('legacy_col_lastname', 'block_configurable_reports'),
            get_string('legacy_col_email', 'block_configurable_reports'),
            get_string('legacy_col_dedication_seconds', 'block_configurable_reports'),
            get_string('legacy_col_dedication_formatted', 'block_configurable_reports'),
            get_string('legacy_col_graceperiods', 'block_configurable_reports'),
            get_string('legacy_col_coursehours', 'block_configurable_reports'),
            get_string('legacy_col_passhours', 'block_configurable_reports'),
            get_string('legacy_col_trackingmethod', 'block_configurable_reports'),
            get_string('legacy_col_timemodified', 'block_configurable_reports'),
        ];

        foreach ($headers as $col => $header) {
            $datasheet->write_string(0, $col, $header, $headerformat);
        }

        // Data rows.
        $row = 1;
        foreach ($data as $record) {
            $datasheet->write_number($row, 0, $record->userid);
            $datasheet->write_string($row, 1, $record->firstname);
            $datasheet->write_string($row, 2, $record->lastname);
            $datasheet->write_string($row, 3, $record->email);
            $datasheet->write_number($row, 4, (int)$record->dedicationtime);
            $datasheet->write_string($row, 5, reader::format_dedication((int)$record->dedicationtime));
            $datasheet->write_number($row, 6, (int)($record->graceperiods ?? 0));
            $datasheet->write_number($row, 7, (float)($record->coursehours ?? 0));
            $datasheet->write_number($row, 8, (float)($record->passhours ?? 0));
            $datasheet->write_string($row, 9, $record->trackingmethod ?? '');
            $datasheet->write_string($row, 10, $record->timemodified
                ? userdate((int)$record->timemodified) : '');
            $row++;
        }

        // Legacy watermark row.
        $row += 2;
        $datasheet->write_string($row, 0,
            get_string('legacy_watermark', 'block_configurable_reports', userdate(time())),
            $legacyformat
        );

        $workbook->close();
        die();
    }

    /**
     * Export SCORM times to Excel.
     *
     * @param string $filename The filename without extension.
     */
    public function export_scorm_excel(string $filename = 'legacy_scorm'): void {
        $data = $this->reader->get_scorm_times();
        $summary = $this->reader->get_executive_summary();

        $filename = clean_filename($filename . '_' . $this->courseid);
        $workbook = new \MoodleExcelWorkbook('-');
        $workbook->send($filename . '.xlsx');

        // Metadata sheet.
        $metasheet = $workbook->add_worksheet(
            get_string('legacy_export_metadata', 'block_configurable_reports')
        );
        $this->write_metadata_sheet($metasheet, $summary);

        // Data sheet.
        $datasheet = $workbook->add_worksheet('SCORM');

        $headerformat = $workbook->add_format([
            'bold' => 1,
            'bg_color' => '#0ABCC9',
            'color' => '#FFFFFF',
        ]);

        $headers = [
            get_string('legacy_col_userid', 'block_configurable_reports'),
            get_string('legacy_col_firstname', 'block_configurable_reports'),
            get_string('legacy_col_lastname', 'block_configurable_reports'),
            get_string('legacy_col_scormname', 'block_configurable_reports'),
            get_string('legacy_col_scoid', 'block_configurable_reports'),
            get_string('legacy_col_attempt', 'block_configurable_reports'),
            get_string('legacy_col_dedication_seconds', 'block_configurable_reports'),
            get_string('legacy_col_dedication_formatted', 'block_configurable_reports'),
            get_string('legacy_col_timemodified', 'block_configurable_reports'),
        ];

        foreach ($headers as $col => $header) {
            $datasheet->write_string(0, $col, $header, $headerformat);
        }

        $row = 1;
        foreach ($data as $record) {
            $datasheet->write_number($row, 0, $record->userid);
            $datasheet->write_string($row, 1, $record->firstname);
            $datasheet->write_string($row, 2, $record->lastname);
            $datasheet->write_string($row, 3, $record->scormname ?? '');
            $datasheet->write_number($row, 4, (int)($record->scoid ?? 0));
            $datasheet->write_number($row, 5, (int)($record->attempt ?? 0));
            $datasheet->write_number($row, 6, (int)($record->dedicationtime ?? 0));
            $datasheet->write_string($row, 7, reader::format_dedication((int)($record->dedicationtime ?? 0)));
            $datasheet->write_string($row, 8, !empty($record->timemodified)
                ? userdate((int)$record->timemodified) : '');
            $row++;
        }

        $workbook->close();
        die();
    }

    /**
     * Export the executive summary to Excel.
     *
     * @param string $filename The filename without extension.
     */
    public function export_summary_excel(string $filename = 'legacy_summary'): void {
        $summary = $this->reader->get_executive_summary();

        $filename = clean_filename($filename . '_' . $this->courseid);
        $workbook = new \MoodleExcelWorkbook('-');
        $workbook->send($filename . '.xlsx');

        $sheet = $workbook->add_worksheet(
            get_string('legacy_export_summary', 'block_configurable_reports')
        );

        $this->write_metadata_sheet($sheet, $summary);

        $workbook->close();
        die();
    }

    /**
     * Write the metadata/summary sheet common to all exports.
     *
     * @param object $sheet The worksheet object.
     * @param \stdClass $summary The executive summary object.
     */
    private function write_metadata_sheet($sheet, \stdClass $summary): void {
        $titleformat = new \stdClass();
        $boldformat = null;

        $row = 0;
        $sheet->write_string($row, 0,
            get_string('legacy_export_title', 'block_configurable_reports'));
        $row++;
        $sheet->write_string($row, 0,
            get_string('legacy_watermark', 'block_configurable_reports', userdate(time())));
        $row += 2;

        // Scenario info.
        $sheet->write_string($row, 0,
            get_string('legacy_export_scenario', 'block_configurable_reports'));
        $sheet->write_string($row, 1, $this->scenarioinfo->label);
        $row++;

        $sheet->write_string($row, 0,
            get_string('legacy_export_source', 'block_configurable_reports'));
        $sheet->write_string($row, 1, $this->scenarioinfo->source);
        $row++;

        $sheet->write_string($row, 0,
            get_string('legacy_export_readonly', 'block_configurable_reports'));
        $sheet->write_string($row, 1, $this->scenarioinfo->readonly
            ? get_string('yes') : get_string('no'));
        $row += 2;

        // Course info.
        if (!empty($summary->course_fullname)) {
            $sheet->write_string($row, 0,
                get_string('legacy_export_course', 'block_configurable_reports'));
            $sheet->write_string($row, 1, $summary->course_fullname);
            $row++;
        }

        if (!empty($summary->course_startdate)) {
            $sheet->write_string($row, 0,
                get_string('legacy_export_startdate', 'block_configurable_reports'));
            $sheet->write_string($row, 1, userdate($summary->course_startdate));
            $row++;
        }

        if (!empty($summary->course_enddate)) {
            $sheet->write_string($row, 0,
                get_string('legacy_export_enddate', 'block_configurable_reports'));
            $sheet->write_string($row, 1, userdate($summary->course_enddate));
            $row++;
        }

        $row++;

        // Stats.
        if (!empty($summary->has_data)) {
            $sheet->write_string($row, 0,
                get_string('legacy_export_students', 'block_configurable_reports'));
            $sheet->write_number($row, 1, $summary->total_students);
            $row++;

            $sheet->write_string($row, 0,
                get_string('legacy_export_avgdedication', 'block_configurable_reports'));
            $sheet->write_string($row, 1, reader::format_dedication($summary->avg_dedication));
            $row++;

            if (!empty($summary->course_hours)) {
                $sheet->write_string($row, 0,
                    get_string('legacy_export_coursehours', 'block_configurable_reports'));
                $sheet->write_number($row, 1, $summary->course_hours);
                $row++;
            }

            if (!empty($summary->first_record)) {
                $sheet->write_string($row, 0,
                    get_string('legacy_export_firstrecord', 'block_configurable_reports'));
                $sheet->write_string($row, 1, userdate($summary->first_record));
                $row++;
            }

            if (!empty($summary->last_record)) {
                $sheet->write_string($row, 0,
                    get_string('legacy_export_lastrecord', 'block_configurable_reports'));
                $sheet->write_string($row, 1, userdate($summary->last_record));
                $row++;
            }
        }

        $row += 2;
        $sheet->write_string($row, 0,
            get_string('legacy_export_generated', 'block_configurable_reports'));
        $sheet->write_string($row, 1, userdate(time()));
    }
}
