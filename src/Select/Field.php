<?php declare(strict_types = 1);

namespace Shredio\DoctrineQueries\Select;

/**
 * @internal
 */
final readonly class Field
{

	private int|false $fieldPos;

	public string $name;

	public function __construct(
		public string $selector,
	)
	{
		$this->fieldPos = strrpos($this->selector, '.');

		if ($this->fieldPos === false) {
			$this->name = $this->selector;
		} else {
			$this->name = substr($this->selector, $this->fieldPos + 1);
		}
	}

	public function getType(): FieldSelectType
	{
		return match ($this->getField()) {
			'*' => FieldSelectType::SelectAll,
			'**' => FieldSelectType::SelectAllWithRelations,
			default => FieldSelectType::Field,
		};
	}

	public function isSelection(): bool
	{
		return $this->getType() !== FieldSelectType::Field;
	}

	public function getField(): string
	{
		return $this->name;
	}

	public function withField(string $field): self
	{
		if ($this->fieldPos === false) {
			return new self($field);
		}

		return new self(substr($this->selector, 0, $this->fieldPos + 1) . $field);
	}

	public function getParent(): ?string
	{
		if ($this->fieldPos === false) {
			return null;
		}

		return substr($this->selector, 0, $this->fieldPos);
	}

	public function hasParent(): bool
	{
		return $this->fieldPos !== false;
	}

}
