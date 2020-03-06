<?php
class CotisationsCollection
{
	protected $doc;
	protected $config;

	public function __construct($config, $doc)
	{
		$this->config = $config;
		$this->doc = $doc;
	}

	public function getCotisations() {
		$cotisations = array();
		$total = 0;
		foreach($this->getDetails() as $detail) {
			$cotisation = $detail->getInstanceCotisation($this->getDoc());

			if(!$cotisation) {
				continue;
			}

			$total += $cotisation->getTotal();
			$cotisations[] = $cotisation;
		}
		if($this->config->exist('minimum') && ($minimum = $this->config->minimum)
      && $this->config->exist('minimum_fallback') && ($minimum_fallback_name = $this->config->minimum_fallback)
			&& $this->config->getDocument()->cotisations->exist($minimum_fallback_name) && ($minimum_fallback = $this->config->getDocument()->cotisations->$minimum_fallback_name)
			&& ($total <= $minimum)){
				return $minimum_fallback->generateCotisations($this->getDoc());
		}

		return $cotisations;
	}

	public function getTotal() {
		$total = 0;
		foreach($this->getCotisations() as $cotisation) {
			$total += $cotisation->getTotal();
		}

		return $total;
	}

	protected function getDoc() {

		return $this->doc;
	}

	protected function getDetails()
	{
		return $this->config->details;
	}
}
