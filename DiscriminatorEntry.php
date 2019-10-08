<?php


namespace RHarink\Doctrine\DiscriminatorEntry;


/**
 * Class DiscriminatorEntry
 * added Target Annotation that it only should have effect on class annotations
 * @Annotation
 * @Target({"CLASS"})
 * @Attributes({
 *   @Attribute("value", type = "string"),
 * })
 */
class DiscriminatorEntry
{
    /**
     * @var string
     */
    private $value;

    public function __construct(array $data)
    {
        $this->value = $data['value'];
    }

    public function getValue(): string
    {
        return $this->value;
    }
}