<?php if (! empty($columns)): ?>

    <div class="form-group">
        <h4>Field Mappings</h4>
        <p class="help-block">Enter the Faker provider for each field (e.g., <code>name</code>, <code>email</code>, <code>address</code>). The parentheses are not needed.</p>
    </div>

    <?php foreach ($columns as $column): ?>
        <div class="form-group span-full">
            <label for="mappings-<?= e($column) ?>">
                <?= e(ucwords(str_replace('_', ' ', $column))) ?>
            </label>
            <input
                type="text"
                id="mappings-<?= e($column) ?>"
                name="mappings[<?= e($column) ?>]"
                class="form-control"
                placeholder="e.g., name"
            />
        </div>
    <?php endforeach ?>

<?php else: ?>

    <div class="callout fade in callout-info no-subheader">
        <div class="header">
            <i class="icon-info"></i>
            <h3>Select a model to see its available fields.</h3>
        </div>
    </div>

<?php endif ?>
