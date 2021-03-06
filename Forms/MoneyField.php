<?php

namespace SilverStripe\Forms;

use SilverStripe\ORM\ArrayLib;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBMoney;
use SilverStripe\ORM\DataObjectInterface;

/**
 * A form field that can save into a {@link Money} database field.
 * See {@link CurrencyField} for a similiar implementation
 * that can save into a single float database field without indicating the currency.
 *
 * @author Ingo Schommer, SilverStripe Ltd. (<firstname>@silverstripe.com)
 */
class MoneyField extends FormField {

	// TODO replace with `FormField::SCHEMA_DATA_TYPE_TEXT` when MoneyField is implemented
	protected $schemaDataType = 'MoneyField';

	/**
	 * @var string $_locale
	 */
	protected $_locale;

	/**
	 * Limit the currencies
	 * @var array $allowedCurrencies
	 */
	protected $allowedCurrencies;

	/**
	 * @var NumericField
	 */
	protected $fieldAmount = null;

	/**
	 * @var FormField
	 */
	protected $fieldCurrency = null;

	/**
	 * Gets field for the currency selector
	 *
	 * @return FormField
	 */
	public function getCurrencyField() {
		return $this->fieldCurrency;
	}

	/**
	 * Gets field for the amount input
	 *
	 * @return NumericField
	 */
	public function getAmountField() {
		return $this->fieldAmount;
	}

	public function __construct($name, $title = null, $value = "") {
		$this->setName($name);

		// naming with underscores to prevent values from actually being saved somewhere
		$this->fieldAmount = new NumericField("{$name}[Amount]", _t('MoneyField.FIELDLABELAMOUNT', 'Amount'));
		$this->fieldCurrency = $this->buildCurrencyField();

		parent::__construct($name, $title, $value);
	}

	public function __clone()
	{
		$this->fieldAmount = clone $this->fieldAmount;
		$this->fieldCurrency = clone $this->fieldCurrency;
	}

	/**
	 * Builds a new currency field based on the allowed currencies configured
	 *
	 * @return FormField
	 */
	protected function buildCurrencyField() {
		$name = $this->getName();
		$allowedCurrencies = $this->getAllowedCurrencies();
		if($allowedCurrencies) {
			$field = new DropdownField(
				"{$name}[Currency]",
				_t('MoneyField.FIELDLABELCURRENCY', 'Currency'),
				ArrayLib::is_associative($allowedCurrencies)
					? $allowedCurrencies
					: array_combine($allowedCurrencies,$allowedCurrencies)
			);
		} else {
			$field = new TextField(
				"{$name}[Currency]",
				_t('MoneyField.FIELDLABELCURRENCY', 'Currency')
			);
		}

		$field->setReadonly($this->isReadonly());
		$field->setDisabled($this->isDisabled());
		return $field;
	}

	public function setValue($val) {
		$this->value = $val;

		if(is_array($val)) {
			$this->fieldCurrency->setValue($val['Currency']);
			$this->fieldAmount->setValue($val['Amount']);
		} elseif($val instanceof DBMoney) {
			$this->fieldCurrency->setValue($val->getCurrency());
			$this->fieldAmount->setValue($val->getAmount());
		}

		// @todo Format numbers according to current locale, incl.
		//  decimal and thousands signs, while respecting the stored
		//  precision in the database without truncating it during display
		//  and subsequent save operations

		return $this;
	}

	/**
	 * 30/06/2009 - Enhancement:
	 * SaveInto checks if set-methods are available and use them
	 * instead of setting the values in the money class directly. saveInto
	 * initiates a new Money class object to pass through the values to the setter
	 * method.
	 *
	 * (see @link MoneyFieldTest_CustomSetter_Object for more information)
	 *
	 * @param DataObjectInterface|Object $dataObject
	 */
	public function saveInto(DataObjectInterface $dataObject) {
		$fieldName = $this->getName();
		if($dataObject->hasMethod("set$fieldName")) {
			$dataObject->$fieldName = DBField::create_field('Money', array(
				"Currency" => $this->fieldCurrency->dataValue(),
				"Amount" => $this->fieldAmount->dataValue()
			));
		} else {
			$currencyField = "{$fieldName}Currency";
			$amountField = "{$fieldName}Amount";

			$dataObject->$currencyField = $this->fieldCurrency->dataValue();
			$dataObject->$amountField = $this->fieldAmount->dataValue();
		}
	}

	/**
	 * Returns a readonly version of this field.
	 */
	public function performReadonlyTransformation() {
		$clone = clone $this;
		$clone->fieldAmount = $clone->fieldAmount->performReadonlyTransformation();
		$clone->fieldCurrency = $clone->fieldCurrency->performReadonlyTransformation();
		$clone->setReadonly(true);
		return $clone;
	}

	public function setReadonly($bool) {
		parent::setReadonly($bool);

		$this->fieldAmount->setReadonly($bool);
		$this->fieldCurrency->setReadonly($bool);

		return $this;
	}

	public function setDisabled($bool) {
		parent::setDisabled($bool);

		$this->fieldAmount->setDisabled($bool);
		$this->fieldCurrency->setDisabled($bool);

		return $this;
	}

	/**
	 * @param array $arr
	 * @return $this
	 */
	public function setAllowedCurrencies($arr) {
		$this->allowedCurrencies = $arr;

		// @todo Has to be done twice in case allowed currencies changed since construction
		$oldVal = $this->fieldCurrency->dataValue();
		$this->fieldCurrency = $this->buildCurrencyField();
		$this->fieldCurrency->setValue($oldVal);

		return $this;
	}

	/**
	 * @return array
	 */
	public function getAllowedCurrencies() {
		return $this->allowedCurrencies;
	}

	public function setLocale($locale) {
		$this->_locale = $locale;
		return $this;
	}

	public function getLocale() {
		return $this->_locale;
	}

	/**
	 * Validate this field
	 *
	 * @param Validator $validator
	 * @return bool
	 */
	public function validate($validator) {
		return !(is_null($this->fieldAmount) || is_null($this->fieldCurrency));
	}

	public function setForm($form) {
		$this->fieldCurrency->setForm($form);
		$this->fieldAmount->setForm($form);
		return parent::setForm($form);
	}
}
