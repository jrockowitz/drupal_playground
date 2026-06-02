<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov_ai_rag_search_chat\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Functional tests for ClinicalTrials.gov AI RAG Search Chat overrides.
 *
 * @group clinical_trials_gov_ai_rag_search_chat
 */
#[Group('clinical_trials_gov_ai_rag_search_chat')]
class ClinicalTrialsGovAiRagSearchChatTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'clinical_trials_gov_ai_rag_search_chat',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that the copied SDC chat interface focuses labels on trial matching.
   */
  public function testChatInterfaceLabels(): void {
    $this->config('ai_rag_search_chat.settings')
      ->set('chat.page_title', 'Find a Clinical Trial')
      ->save();

    $this->drupalLogin($this->drupalCreateUser([
      'access ai rag search chat',
    ]));

    $this->drupalGet('ai-search/chat');

    // Check that the page title is focused on the user's trial-finding goal.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Find a Clinical Trial');

    // Check that the copied ClinicalTrials.gov chat interface guides intake.
    $this->assertSession()->pageTextContains('Tell us what kind of trial you’re looking for');
    $this->assertSession()->pageTextContains('Share what you know: diagnosis, age, location, prior treatments, biomarkers, and travel preferences.');
    $this->assertSession()->elementExists('css', 'textarea#chat-message-input[placeholder="Condition, age, location, prior treatments, biomarkers..."]');
    $this->assertSession()->elementNotExists('css', 'textarea#chat-message-input[placeholder="Ask a question..."]');
    $this->assertSession()->buttonExists('Find trials');

    // Check that the copied ClinicalTrials.gov sidebar uses trial-search labels.
    $this->assertSession()->buttonExists('New trial search');
    $this->assertSession()->fieldExists('Search previous trial searches...');
    $this->assertSession()->linkExists('Back to trial search');
    $this->assertSession()->pageTextContains('Trial search history is kept for');
    $this->assertSession()->pageTextContains('Recent Trial Searches');
    $this->assertSession()->pageTextContains('Loading trial searches...');
    $this->assertSession()->pageTextContains('No previous trial searches');
    $this->assertSession()->buttonExists('Load more searches');
    $this->assertSession()->elementExists('css', '#chat-sidebar-panel[aria-label="Trial search sessions"]');
    $this->assertSession()->elementExists('css', '.sidebar-toggle[aria-label="Open trial search history"]');
    $this->assertSession()->elementExists('css', '.clear-search-button[aria-label="Clear previous search filter"]');
  }

}
