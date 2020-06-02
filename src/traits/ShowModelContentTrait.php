<?php


namespace AlexVenga\FirstStupidORM\Traits;

trait ShowModelContentTrait
{

    /*
    public function __toString()
    {

        $string = sprintf("%s%s%s%s\n", Color::GREEN, Color::BOLD, static::class, Color::RESET);

        $reflect = new \ReflectionClass($this);
        $properties = $reflect->getProperties();

        foreach ($properties as $property) {

            $propertyName = $property->getName();

            if ($property->isStatic()) {
                $value = $this::$$propertyName;
            } else {
                $value = $this->$propertyName;
            }

            if (is_null($value)) {
                continue;
            }

            $string .= sprintf("    %s%s%s%s ", Color::DARK_GRAY, Color::UNDERLINED, $propertyName, Color::RESET);
            $string .= sprintf('%s ', ucfirst(gettype($value)));
            $string .= Color::BLUE;
            if ($property->isPrivate()) {
                $string .= 'private ';
            } elseif ($property->isProtected()) {
                $string .= 'protected ';
            } elseif ($property->isPublic()) {
                $string .= 'public ';
            }
            if ($property->isStatic()) {
                $string .= 'static ';
            }
            $string .= Color::RESET;

            $string .= PHP_EOL;

            if (is_scalar($value)) {
                $string .= sprintf('    %s', $value);
            } elseif (is_array($value)) {
                $string .= sprintf('    (%s)', ucfirst(gettype($value)));
            } else {
                $string .= sprintf('    (%s)', ucfirst(gettype($value)));
            }

            $string .= PHP_EOL;
        }

        return $string;

    }
    */

}