<?php

namespace ImportWP\Common\Importer\File;

use ImportWP\Common\Importer\FileInterface;

class CSVFile extends AbstractIndexedFile implements FileInterface
{

	protected $enclosure = '"';
	protected $delimiter = ',';


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

	/**
	 * Generate record file positions
	 *
	 * Loop through each record and save each position
	 */
	protected function generateIndex()
	{
		$record = 0;
		rewind($this->getFileHandle());
		while (!feof($this->getFileHandle())) {

			$startIndex = ftell($this->getFileHandle());
			$row = fgetcsv($this->getFileHandle(), 0, $this->getDelimiter(), $this->getEnclosure());

			if (!empty($row)) {
				$this->setIndex($record, $startIndex, ftell($this->getFileHandle()));
				$record++;
			}

			if ($this->is_processing && ($record >= 2 || $startIndex > $this->process_max_size)) {
				break;
			}
		}
	}
}
