<?php

namespace Tests\Unit;

use App\Services\Quiz\AnswerChecker;
use PHPUnit\Framework\TestCase;

class AnswerCheckerTest extends TestCase
{
    private AnswerChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new AnswerChecker;
    }

    public function test_typing_is_case_insensitive_and_article_optional(): void
    {
        $this->assertTrue($this->checker->typingMatches('handynummer', 'die Handynummer'));
        $this->assertTrue($this->checker->typingMatches('Die HANDYNUMMER', 'die Handynummer'));
        $this->assertTrue($this->checker->typingMatches('  die Handynummer ', 'die Handynummer'));
    }

    public function test_typing_is_umlaut_tolerant(): void
    {
        $this->assertTrue($this->checker->typingMatches('Strasse', 'die Straße'));
        $this->assertTrue($this->checker->typingMatches('Studienfaecher', 'Studienfächer'));
        $this->assertTrue($this->checker->typingMatches('heissen', 'heißen'));
        $this->assertTrue($this->checker->typingMatches('gross', 'groß'));
    }

    public function test_typing_ignores_parentheticals_and_accepts_variants(): void
    {
        $this->assertTrue($this->checker->typingMatches('vorstellen', '(sich) vorstellen'));
        $this->assertTrue($this->checker->typingMatches('Postleitzahl', 'die Postleitzahl (PLZ)'));
        $this->assertTrue($this->checker->typingMatches('Rechtswissenschaften', 'Jura / die Rechtswissenschaften'));
        $this->assertTrue($this->checker->typingMatches('Wie', 'Wie?'));
    }

    public function test_typing_rejects_wrong_or_empty_answers(): void
    {
        $this->assertFalse($this->checker->typingMatches('Telefonnummer', 'die Handynummer'));
        $this->assertFalse($this->checker->typingMatches('', 'die Handynummer'));
        $this->assertFalse($this->checker->typingMatches(null, 'die Handynummer'));
        $this->assertFalse($this->checker->typingMatches('der', 'der Name'));
    }

    public function test_option_match_is_exact(): void
    {
        $this->assertTrue($this->checker->optionMatches('der', 'der'));
        $this->assertFalse($this->checker->optionMatches('die', 'der'));
        $this->assertFalse($this->checker->optionMatches(null, 'der'));
    }
}
