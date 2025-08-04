<button
    class="btn btn-sm btn-secondary"
    data-request="onGenerateSingle"
    data-request-data="seed_id: '<?= $record->id ?>'"
    data-load-indicator="<?= e(__('Generating...')) ?>"
    >
    <?= e(__('Generate')) ?>
</button>