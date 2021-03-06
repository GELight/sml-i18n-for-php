<?php

namespace GELight\smlI18n;

use Stenway\Sml\{SmlDocument, SmlElement};
use \ResourceBundle;
use \Exception;
use \NumberFormatter;

class smlI18n {

    private string $defaultLocale;
    private string $currentLocale;
    private string|bool $forcedCallbackLocale = false;
    private array $translations = array();
    private array $timeZones = array();
    private string $originalKey;

    private array $locales;

    public function __construct(string $path = null, string $currentLocale = null) {
        $this->prepareAvailableLocales();
        $this->prepareAvailableTimeZones();
        $this->setDefaultLocale("de");
        $this->setCurrentLocale($this->getDefaultLocale());

        if (is_string($path)) {
            $this->loadTranslations($path);
        }
        if (is_string($currentLocale)) {
            $this->setCurrentLocale($currentLocale);
        }
    }

    private function prepareAvailableLocales(): void {
        $this->locales = ResourceBundle::getLocales('');
    }

    private function prepareAvailableTimeZones(): void {
        $this->timeZones = timezone_identifiers_list();
    }

    public function isValidLocale(string $locale): bool {
        return in_array($locale, $this->locales);
    }

    public function setDefaultLocale(string $locale): smlI18n {
        if (in_array($locale, $this->locales)) {
            $this->defaultLocale = $locale;
        } else {
            $this->defaultLocale = "de";
            die("Unknown local '".$locale."' defined! The default locale has been set to '".$this->defaultLocale."'.");
        }

        return $this;
    }

    public function getDefaultLocale(): string {
        return $this->defaultLocale;
    }

    public function setCurrentLocale(string $locale): smlI18n {
        if (in_array($locale, $this->locales)) {
            $this->currentLocale = $locale;
        } else {
            $this->currentLocale = $this->getDefaultLocale();
            die("Unknown local '".$locale."' defined! The current locale has been set to '".$this->currentLocale."'.");
        }

        return $this;
    }

    public function getCurrentLocale(): string {
        return $this->currentLocale;
    }

    public function loadTranslations(string $path): smlI18n {
        if ($handle = opendir($path)) {
            while (false !== ($entry = readdir($handle))) {
                $ext = pathinfo($entry, PATHINFO_EXTENSION);
                if ($entry != "." && $entry != ".." && $ext === "sml") {
                    $smlDocument = SmlDocument::load($path."/".$entry);
                    foreach ($smlDocument->getRoot()->elements() as $element) {
                        $this->translations[$element->getName()] = $element;
                    }
                }
            }
            closedir($handle);
        }

        return $this;
    }

    public function setForcedCallbackLocale(string $locale): smlI18n {
        $this->forcedCallbackLocale = $this->isValidLocale($locale) ? $locale : $this->getDefaultLocale();
        return $this;
    }

    public function getForcedCallbackLocale(): string|bool {
        return $this->forcedCallbackLocale;
    }

    public function t(string $key, array $replaceWith = []): string {
        $translations = $this->translations[$this->getLocale()];

        return $this->getTranslationValue($key, $translations, $replaceWith);
    }

    private function getLocale(): string {
        $locale = $this->currentLocale;

        if (isset($this->translations[$locale])) {
            return $locale;
        } elseif (is_string($this->getForcedCallbackLocale()) && $this->translations[$this->getForcedCallbackLocale()]) {
            return $this->getForcedCallbackLocale();
        } else {
            if (str_contains($locale, "_")) {
                $localeCallback = explode("_", $locale)[0];
                if (isset($this->translations[$localeCallback])) {
                    return $localeCallback;
                }
            }
        }

        if (!isset($this->translations[$this->defaultLocale])) {
            die("No translations defined for '".$this->defaultLocale."'");
        }

        return $this->defaultLocale;
    }

    private function getTranslationValue(string $key, SmlElement $translations, array $replaceWith): string {
        $value = "";

        if (!str_contains($key, ".")) {
            if ($translations->hasAttribute($key)) {
                $value = $translations->attribute($key)->getValues()[0];
                $value = $this->resolvePlaceholdersInTranslation($value, $replaceWith);
            }
        } else {
            $currentKeys = explode(".", $key);
            $nextElement = array_shift($currentKeys);
            $nextKeyName = implode(".", $currentKeys);

            if ($translations->hasElement($nextElement)) {
                $value = $this->getTranslationValue($nextKeyName, $translations->element($nextElement), $replaceWith);
            }
        }

        return $value;
    }

    private function resolvePlaceholdersInTranslation(string $translationString, array $replaceWith): string {
        $resolvedString = $translationString;

        preg_match_all("|{(.*)}|U", $resolvedString, $results, PREG_SET_ORDER);

        if (count($results) > 0) {
            foreach ($results as $placeholder) {
                if (isset($replaceWith[$placeholder[1]])) {
                    $resolvedString = $this->replacePlaceholder($placeholder[0], $replaceWith[$placeholder[1]], $resolvedString);
                } else {
                    $value = $this->getTranslationValue($placeholder[1], $this->translations[$this->getLocale()], $replaceWith);
                    $resolvedString = $this->replacePlaceholder($placeholder[0], $value, $resolvedString);
                }
            }
        }

        return $resolvedString;
    }

    private function replacePlaceholder(string $placeholder, string $value, string $translation): string {
        return str_replace($placeholder, $value, $translation);
    }

}
