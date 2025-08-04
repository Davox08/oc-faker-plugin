<div data-control="toolbar loader-container">
    <a
        href="<?= Backend::url('davox/faker/seeds/create') ?>"
        class="btn btn-primary">
        <i class="icon-plus"></i>
        <?= __('New :name', ['name' => 'Seed']) ?>
    </a>

    <div class="toolbar-divider"></div>

    <button
        class="btn btn-secondary"
        data-request="onDelete"
        data-request-message="<?= __('Deleting...') ?>"
        data-request-confirm="<?= __('Are you sure?') ?>"
        data-list-checked-trigger
        data-list-checked-request
        disabled>
        <i class="icon-delete"></i>
        <?= __('Delete') ?>
    </button>

    <div class="toolbar-divider"></div>

    <button
        class="btn btn-default oc-icon-magic"
        data-request="onGenerateAll"
        data-request-confirm="Are you sure you want to generate data for ALL configurations?"
        data-load-indicator="Generating all data..."
        data-request-success="$.oc.flashMsg({ text: 'Data generation process completed.', class: 'success' })"
        >
        Generate All Data
    </button>
</div>
