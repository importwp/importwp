<?php

namespace ImportWP\Common\Queue\Action;

use ImportWP\Common\Importer\Exception\FileException;
use ImportWP\Common\Importer\Exception\MapperException;
use ImportWP\Common\Importer\Exception\ParserException;
use ImportWP\Common\Importer\Exception\RecordUpdatedSkippedException;
use ImportWP\Common\Queue\QueueTaskResult;
use ImportWP\Common\Util\Logger;

class ImportAction implements ActionInterface
{
    public $chunk;
    public $data_parser;
    public $importer;

    public function __construct($chunk, $data_parser, $importer)
    {
        $this->chunk = $chunk;
        $this->data_parser = $data_parser;
        $this->importer = $importer;
    }

    public function handle()
    {
        $i = $this->chunk['pos'];

        /**
         * @var ParsedData $data
         */
        $data = null;

        $record_id = 0;
        $import_type = '';
        $message = '';

        try {

            $data = $this->data_parser->get($i);
            do_action('iwp/importer/before_row', $data);

            $skip_record = $this->importer->filterRecords();
            $skip_record = apply_filters('iwp/importer/skip_record', $skip_record, $data, $this->importer);

            if ($skip_record) {

                Logger::write('import -skip-record=' . $i);
                $import_type = 'S';
                $message = 'Record Skipped';
                $message = apply_filters('iwp/status/record_skipped', $message);
                $data = null;
            } else {

                // import
                $data = apply_filters('iwp/importer/before_mapper', $data, $this->importer);
                $data->map();

                if ($data->isInsert()) {
                    Logger::write('import:' . $i . ' -success -insert');
                    $import_type = 'I';
                    $record_id = $data->getId();
                    $message = apply_filters('iwp/status/record_inserted', 'Record Inserted: #' . $data->getId(), $data->getId(), $data);
                    $message .= $this->importer->get_unique_identifier_log_text();
                }

                if ($data->isUpdate()) {
                    Logger::write('import:' . $i . ' -success -update');
                    $import_type = 'U';
                    $record_id = $data->getId();
                    $message = apply_filters('iwp/status/record_updated', 'Record Updated: #' . $data->getId(), $data->getId(), $data);
                    $message .= $this->importer->get_unique_identifier_log_text();
                }
            }
        } catch (RecordUpdatedSkippedException $e) {

            Logger::write('import:' . $i . ' -success -update -skipped="hash"');
            $import_type = 'S';
            $message = 'Record Update Skipped: #' . $data->getId() . ' ' . $e->getMessage();
            $message = apply_filters('iwp/status/record_skipped', $message);
        } catch (ParserException $e) {

            $import_type = 'N';

            Logger::error('import:' . $i . ' -parser-error=' . $e->getMessage());
            $message = 'Record Error: #' . $data->getId() . ' ' . $e->getMessage();
        } catch (MapperException $e) {

            $import_type = 'N';

            Logger::error('import:' . $i . ' -mapper-error=' . $e->getMessage());
            $message = 'Record Error: #' . $data->getId() . ' ' . $e->getMessage();
        } catch (FileException $e) {

            $import_type = 'N';

            Logger::error('import:' . $i . ' -file-error=' . $e->getMessage());
            $message = 'Record Error: #' . $data->getId() . ' ' . $e->getMessage();
        }

        do_action('iwp/importer/after_row');

        return new QueueTaskResult($record_id, $import_type, $message);
    }
}
