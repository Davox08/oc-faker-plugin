<div data-control="toolbar loader-container">
    <a
        href="<?= Backend::url('davo/faker/seeds/create') ?>"
        class="btn btn-primary">
        <i class="icon-plus"></i>
        <?= e(__('New :name', ['name' => __('Seed')])) ?>
    </a>

    <div class="toolbar-divider"></div>

    <button
        class="btn btn-secondary"
        data-request="onDelete"
        data-request-message="<?= e(__('Deleting...')) ?>"
        data-request-confirm="<?= e(__('Are you sure?')) ?>"
        data-list-checked-trigger
        data-list-checked-request
        disabled>
        <i class="icon-delete"></i>
        <?= e(__('Delete')) ?>
    </button>

    <div class="toolbar-divider"></div>

    <button
        class="btn btn-default oc-icon-magic"
        data-request="onGenerateAll"
        data-request-confirm="<?= e(__('Are you sure you want to generate data for ALL configurations?')) ?>"
        data-load-indicator="<?= e(__('Generating all data...')) ?>"
        data-request-success="$.oc.flashMsg({ text: '<?= e(__('Data generation process completed.')) ?>', class: 'success' })"
        >
        <?= e(__('Generate All Data')) ?>
    </button>
</div>
