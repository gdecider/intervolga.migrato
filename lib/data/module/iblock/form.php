<? namespace Intervolga\Migrato\Data\Module\Iblock;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Data\BaseData;
use Intervolga\Migrato\Data\Link;
use Intervolga\Migrato\Data\Record;
use Bitrix\Iblock\PropertyTable;

Loc::loadMessages(__FILE__);

class Form extends BaseData
{
	const CATEGORY = 'form';
	const USER_ADMIN = 1;

	const NAME_ELEMENT = 'form_element_';
	const NAME_SECTION = 'form_section_';

	const XML_ALL = 'all';
	const XML_ADMIN = 'admin';
	const XML_ELEMENT = 'el';
	const XML_SECTION = 'sec';

	public function getFilesSubdir()
	{
		return '/type/iblock/';
	}

	public function getDependencies()
	{
		return array(
			'IBLOCK_ID' => new Link(Iblock::getInstance()),
			'PROPERTY_ID' => new Link(Property::getInstance()),
		);
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$filter = array('CATEGORY' => self::CATEGORY);
		$getList = \CUserOptions::getList(array(), $filter);
		while ($form = $getList->fetch())
		{
			if ($record = $this->arrayToRecord($form))
			{
				$result[] = $record;
			}
		}

		return $result;
	}

	/**
	 * @param array $form
	 *
	 * @return \Intervolga\Migrato\Data\Record|null
	 * @throws \Bitrix\Main\NotImplementedException
	 */
	protected function arrayToRecord(array $form)
	{
		if ($formXmlId = $this->fieldsToXmlId($form))
		{
			$record = new Record($this);
			$record->setXmlId($formXmlId);
			$record->setId($this->createId($form['ID']));
			$record->setFieldRaw('VALUE', $form['VALUE']);
			$this->addIblockDependency($record, $form['NAME']);
			$this->addPropsDependencies($record, $form['VALUE']);

			return $record;
		}

		return null;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 * @param string $name
	 *
	 * @throws \Bitrix\Main\NotImplementedException
	 */
	protected function addIblockDependency(Record $record, $name)
	{
		$iblockId = substr($name, strripos($name, '_') + 1);
		if ($iblockId)
		{
			$dependency = clone $this->getDependency('IBLOCK_ID');
			$dependency->setValue(
				Iblock::getInstance()->getXmlId(Iblock::getInstance()->createId($iblockId))
			);
			$record->setDependency('IBLOCK_ID', $dependency);
		}
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 * @param string $value
	 */
	protected function addPropsDependencies(Record $record, $value)
	{
		$propertyXmlIds = array();
		foreach ($this->getIblockProperties() as $id => $xmlId)
		{
			$find = '--PROPERTY_' . $id . '--';
			$replace = '--PROPERTY_' . $xmlId . '--';
			if (is_int(strpos($value, $find)))
			{
				$value = str_replace($find, $replace, $value);
				$propertyXmlIds[] = $xmlId;
			}
		}
		if ($propertyXmlIds)
		{
			$record->setFieldRaw('VALUE', $value);

			$dependency = clone $this->getDependency('PROPERTY_ID');
			$dependency->setValues($propertyXmlIds);
			$record->setDependency('PROPERTY_ID', $dependency);
		}
	}

	/**
	 * @param bool $idToXmlId
	 *
	 * @return array
	 * @throws \Bitrix\Main\ArgumentException
	 */
	protected function getIblockProperties($idToXmlId = true)
	{
		static $properties = array();

		if (!$properties[$idToXmlId])
		{
			$propertiesGetList = PropertyTable::getList(
				array(
					'select' => array(
						'ID',
						'XML_ID',
					)
				)
			);
			while ($property = $propertiesGetList->fetch())
			{
				if ($idToXmlId)
				{
					$properties[$idToXmlId][$property['ID']] = $property['XML_ID'];
				}
				else
				{
					$properties[$idToXmlId][$property['XML_ID']] = $property['ID'];
				}
			}
		}

		return $properties[$idToXmlId];
	}

	/**
	 * @param array $form
	 *
	 * @return string
	 */
	protected function fieldsToXmlId(array $form)
	{
		$xmlIdParts = array();
		if (!$form['USER_ID'] || $form['USER_ID'] == static::USER_ADMIN)
		{
			if ($form['CATEGORY'] == static::CATEGORY)
			{
				$type = '';
				if (is_int(strpos($form['NAME'], static::NAME_ELEMENT)))
				{
					$type = static::XML_ELEMENT;
				}
				elseif (is_int(strpos($form['NAME'], static::NAME_SECTION)))
				{
					$type = static::XML_SECTION;
				}
				if ($type)
				{
					$xmlIdParts[] = $type;

					if ($form['COMMON'] == 'Y')
					{
						$xmlIdParts[] = static::XML_ALL;
					}
					else
					{
						$xmlIdParts[] = static::XML_ADMIN;
					}

					$iblockId = substr($form['NAME'], strripos($form['NAME'], '_') + 1);
					$iblockXmlId = \CIBlock::getByID($iblockId)->fetch();
					$xmlIdParts[] = $iblockXmlId['XML_ID'];
				}
			}
		}

		return strtolower(implode('-', $xmlIdParts));
	}

	protected function parseXmlId($xmlId)
	{
		$result = array();
		$pattern = '/^(?<type>el|sec)-(?<user>admin|all)-(?<iblock>.*)$/';
		$matches = array();
		if (preg_match($pattern, $xmlId, $matches))
		{
			if ($iblockXmlId = $matches['iblock'])
			{
				if ($id = Iblock::getInstance()->findRecord($iblockXmlId))
				{
					$type = '';
					if ($matches['type'] == static::XML_ELEMENT)
					{
						$type = static::NAME_ELEMENT;
					}
					elseif ($matches['type'] == static::XML_SECTION)
					{
						$type = static::NAME_SECTION;
					}
					if ($type)
					{
						$result = array(
							'CATEGORY' => static::CATEGORY,
							'NAME' => $type . $id->getValue(),
							'COMMON' => ($matches['user'] == static::XML_ALL ? 'Y' : 'N'),
							'USER_ID' => ($matches['user'] == static::XML_ADMIN ? static::USER_ADMIN : 0),
						);
					}
				}
			}
		}

		return $result;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Record $record
	 *
	 * @return array
	 * @throws \Exception
	 */
	protected function recordToArray(Record $record)
	{
		$result = $this->parseXmlId($record->getXmlId());
		if ($value = $record->getFieldRaw('VALUE'))
		{
			foreach ($this->getIblockProperties(false) as $xmlId => $id)
			{
				$find = '--PROPERTY_' . $xmlId . '--';
				$replace = '--PROPERTY_' . $id . '--';
				$value = str_replace($find, $replace, $value);
			}
			$result['VALUE'] = $value;
		}

		return $result;
	}

	public function getXmlId($id)
	{
		$filter = array('ID' => $id, 'CATEGORY' => static::CATEGORY);
		$getList = \CUserOptions::getList(array(), $filter);
		if ($form = $getList->fetch())
		{
			return $this->fieldsToXmlId($form);
		}

		return '';
	}

	public function update(Record $record)
	{
		$array = $this->recordToArray($record);
		$options = new \CUserOptions();
		$isUpdated = false;

		if ($array['CATEGORY'] && $array['NAME'])
		{
			$isUpdated = $options->setOption(
				$array['CATEGORY'],
				$array['NAME'],
				unserialize($array['VALUE']),
				$array['COMMON'] == 'Y',
				$array['USER_ID']
			);
		}

		if (!$isUpdated)
		{
			throw new \Exception('INTERVOLGA_MIGRATO.IBLOCK_FORM_NOT_UPDATED');
		}
	}

	public function create(Record $record)
	{
		$array = $this->recordToArray($record);
		if ($array['CATEGORY'] && $array['NAME'])
		{
			$options = new \CUserOptions();
			$isAdded = $options->setOption(
				$array['CATEGORY'],
				$array['NAME'],
				unserialize($array['VALUE']),
				$array['COMMON'] == 'Y',
				$array['USER_ID']
			);

			if ($isAdded)
			{
				$filter = array(
					'CATEGORY' => $array['CATEGORY'],
					'NAME' => $array['NAME'],
					'COMMON' => $array['COMMON'],
					'USER_ID' => $array['USER_ID'],
				);
				$userOption = \CUserOptions::getList(array(), $filter)->fetch();
				if ($userOption)
				{
					return $this->createId($userOption['ID']);
				}
				else
				{
					throw new \Exception('INTERVOLGA_MIGRATO.IBLOCK_FORM_NOT_CREATED');
				}
			}
			else
			{
				throw new \Exception('INTERVOLGA_MIGRATO.IBLOCK_FORM_NOT_CREATED');
			}
		}
		else
		{
			throw new \Exception('INTERVOLGA_MIGRATO.IBLOCK_FORM_NOT_CREATED');
		}
	}

	/**
	 * @param string $xmlId
	 *
	 * @throws \Exception
	 */
	public function delete($xmlId)
	{
		$fields = $this->parseXmlId($xmlId);
		if ($fields)
		{
			if (!\CUserOptions::deleteOption($fields['CATEGORY'], $fields['NAME'], $fields['COMMON'] == 'Y', $fields['USER_ID']))
			{
				throw new \Exception('INTERVOLGA_MIGRATO.IBLOCK_FORM_DELETE_ERROR');
			}
		}
	}

	public function setXmlId($id, $xmlId)
	{}
}