<?php

namespace Tests\Unit;

use App\Services\Import\PluralExpander;
use PHPUnit\Framework\TestCase;

class PluralExpanderTest extends TestCase
{
    public function test_expands_suffixes(): void
    {
        $this->assertSame('Formulare', PluralExpander::expand('Formular', '-e'));
        $this->assertSame('Namen', PluralExpander::expand('Name', '-n'));
        $this->assertSame('Nationalitäten', PluralExpander::expand('Nationalität', '-en'));
        $this->assertSame('Handys', PluralExpander::expand('Handy', '-s'));
        $this->assertSame('Lehrer', PluralExpander::expand('Lehrer', '-'));
        $this->assertSame('Kursteilnehmerinnen', PluralExpander::expand('Kursteilnehmerin', '-nen'));
    }

    public function test_expands_umlaut_marker(): void
    {
        $this->assertSame('Studienfächer', PluralExpander::expand('Studienfach', '¨-er'));
        $this->assertSame('Bücher', PluralExpander::expand('Buch', '¨-er'));
        $this->assertSame('Stühle', PluralExpander::expand('Stuhl', '¨-e'));
        $this->assertSame('Häuser', PluralExpander::expand('Haus', '¨-er'));
        $this->assertSame('Väter', PluralExpander::expand('Vater', '¨-'));
    }

    public function test_full_forms_and_abbreviations(): void
    {
        $this->assertSame('Praktika', PluralExpander::expand('Praktikum', 'Praktika'));
        $this->assertSame('Postleitzahlen', PluralExpander::expand('Postleitzahl (PLZ)', '-en'));
        $this->assertNull(PluralExpander::expand('Rechtswissenschaften', '(Pl.)'));
        $this->assertNull(PluralExpander::expand('Chemie', ''));
    }

    public function test_maps_free_form_categories(): void
    {
        $this->assertSame('Verben', PluralExpander::mapCategory('verben', 'verb'));
        $this->assertSame('Länder-Sprachen-Nationalitäten', PluralExpander::mapCategory('Länder – Sprachen – Nationalitäten', 'country'));
        $this->assertSame('Redemittel', PluralExpander::mapCategory('Redemittel / Ausdrücke', 'expression'));
        $this->assertSame('Adjektive', PluralExpander::mapCategory('???', 'adjective'));
        $this->assertSame('Nomen', PluralExpander::mapCategory('???', 'noun'));
    }
}
