<?php

declare(strict_types=1);

namespace Drupal\ai_devel_cache\Form;

use Drupal\ai_devel_cache\AiDevelCacheManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Reports cached AI provider responses and offers a one-click clear.
 */
class AiDevelCacheReportForm extends FormBase {

  /**
   * Number of leading hash characters to display in the report table.
   */
  const HASH_DISPLAY_LENGTH = 12;

  /**
   * Number of cache entries displayed per page.
   */
  const PAGE_SIZE = 50;

  /**
   * Constructs the form.
   *
   * @param \Drupal\ai_devel_cache\AiDevelCacheManagerInterface $cache
   *   The AI devel cache backend.
   * @param \Drupal\Core\Pager\PagerManagerInterface $pagerManager
   *   The pager manager.
   */
  public function __construct(
    protected AiDevelCacheManagerInterface $cache,
    protected PagerManagerInterface $pagerManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('ai_devel_cache.manager'),
      $container->get('pager.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_devel_cache_report';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $entries = $this->cache->list();
    usort($entries, fn(array $a, array $b) => strcmp($b['cached_at'] ?? '', $a['cached_at'] ?? ''));

    $total_bytes = array_sum(array_column($entries, 'bytes'));
    $timestamps = array_filter(array_column($entries, 'cached_at'));
    $oldest = $timestamps ? min($timestamps) : '';
    $newest = $timestamps ? max($timestamps) : '';

    $form['summary'] = [
      '#type' => 'details',
      '#title' => $this->t('Cache summary'),
      '#open' => FALSE,
    ];
    $form['summary']['items'] = [
      '#theme' => 'item_list',
      '#items' => [
        $this->t('Entries: @count', ['@count' => count($entries)]),
        $this->t('Total size: @bytes', ['@bytes' => ByteSizeMarkup::create($total_bytes)]),
        $this->t('Directory: @directory', ['@directory' => $this->cache->directory()]),
        $this->t('Oldest: @oldest', ['@oldest' => $oldest ?: '-']),
        $this->t('Newest: @newest', ['@newest' => $newest ?: '-']),
      ],
    ];

    $total = count($entries);
    $pager = $this->pagerManager->createPager($total, self::PAGE_SIZE);
    $page = $pager->getCurrentPage();
    $paged_entries = array_slice($entries, $page * self::PAGE_SIZE, self::PAGE_SIZE);
    $start = $total ? ($page * self::PAGE_SIZE) + 1 : 0;
    $end = $start + count($paged_entries) - 1;

    $form['showing'] = [
      '#markup' => $this->t('Showing @start - @end of @total entries', [
        '@start' => $start,
        '@end' => max($end, 0),
        '@total' => $total,
      ]),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
      '#access' => $total > 0,
    ];

    $rows = [];
    foreach ($paged_entries as $entry) {
      $rows[] = [
        'cached_at' => $entry['cached_at'],
        'provider_id' => $entry['provider_id'],
        'model_id' => $entry['model_id'],
        'operation_type' => $entry['operation_type'],
        'tags' => implode(', ', $entry['tags']),
        'input_preview' => $entry['input_preview'],
        'bytes' => ByteSizeMarkup::create($entry['bytes']),
        'hash' => substr($entry['hash'], 0, self::HASH_DISPLAY_LENGTH),
      ];
    }

    $form['entries'] = [
      '#type' => 'table',
      '#header' => [
        'cached_at' => $this->t('Cached at'),
        'provider_id' => $this->t('Provider'),
        'model_id' => $this->t('Model'),
        'operation_type' => $this->t('Operation'),
        'tags' => $this->t('Tags'),
        'input_preview' => $this->t('Input preview'),
        'bytes' => $this->t('Size'),
        'hash' => $this->t('Hash'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No AI provider responses are currently cached.'),
    ];

    $form['pager'] = [
      '#type' => 'pager',
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['clear'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear cache'),
      '#button_type' => 'danger',
      '#access' => !empty($entries),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $deleted = $this->cache->clear();
    $this->messenger()->addStatus($this->t(
      'Deleted @count cached AI @label.',
      [
        '@count' => $deleted,
        '@label' => ($deleted === 1) ? $this->t('response') : $this->t('responses'),
      ],
    ));
  }

}
