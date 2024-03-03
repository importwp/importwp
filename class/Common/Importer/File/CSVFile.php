<?php

namespace ImportWP\Common\Importer\File;

use ImportWP\Common\Importer\FileInterface;
use ImportWP\Common\Importer\State\ImporterState;

class CSVFile extends AbstractIndexedFile implements FileInterface
{

	protected $enclosure = '"';
	protected $delimiter = ',';
	protected $escape = '\\';


	/**
	 * Set Enclosure
	 *
	 * @param string $enclosure
	 */
	public function setEnclosure($enclosure)
	{
		$this->enclosure = $enclosure;
	}

	/**
	 * Set Delimiter
	 *
	 * @param string $delimiter
	 */
	public function setDelimiter($delimiter)
	{
		$this->delimiter = $delimiter;
	}

	public function setEscape($escape)
	{
		$this->escape = $escape;
	}

	/**
	 * Get Delimiter
	 *
	 * @return string
	 */
	public function getDelimiter()
	{
		return $this->delimiter;
	}

	/**
	 * Get enclosure
	 *
	 * @return string
	 */
	public function getEnclosure()
	{
		return $this->enclosure;
	}

	public function getEscape()
	{
		return $this->enclosure === $this->escape ? '' : $this->escape;
	}

	/**
	 * Generate record file positions
	 *
	 * Loop through each record and save each position
	 */
	public function generateIndex()
	{
		$record = 0;

		$last_percent = 0;
		$size = intval($this->get_file_size($this->getFileHandle()));
		$this->config->set('process_max', $size);

		rewind($this->getFileHandle());
		while (!feof($this->getFileHandle())) {

			$startIndex = ftell($this->getFileHandle());
			$row = fgetcsv($this->getFileHandle(), 0, $this->getDelimiter(), $this->getEnclosure(), $this->getEscape());

			if (!empty($row)) {
				$this->setIndex($record, $startIndex, ftell($this->getFileHandle()));
				$record++;
			}

			if ($this->is_processing && ($record >= 2 || $startIndex > $this->process_max_size)) {
				break;
			}

			$current_pos = intval(ftell($this->getFileHandle()));
			if ($size > 0 && $current_pos > 0) {
				$percent = round($current_pos / $size, 2);
				if ($percent > $last_percent) {

					do_action('iwp/importer/process', $last_percent * 100);
					$this->config->set('process', $last_percent * 100);
					$last_percent = $percent;

					if (!is_null(iwp()->importer)) {
						$state = ImporterState::get_state(iwp()->importer->getId());
						if (isset($state['status']) && $state['status'] === 'cancelled') {
							return;
						}
					}
				}
			}
		}
	}
}
