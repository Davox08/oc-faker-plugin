<?php if (! empty($columns)): ?>
    <div class="form-group">
        <p class="help-block">Select the Faker formatter for each field. Fields from the model's `$guarded` property are automatically excluded.</p>
    </div>

    <?php foreach ($columns as $column): ?>
        <div class="form-group span-full">
            <label for="mappings-<?= e($column) ?>">
                <?= e(ucwords(str_replace('_', ' ', $column))) ?>
            </label>
            <select
                id="mappings-<?= e($column) ?>"
                name="Seed[mappings][<?= e($column) ?>]"
                class="form-control custom-select"
                >
                <option value="">-- Don't Map --</option>
                <?php if (! empty($fakerFormatters)): ?>
                    <?php foreach ($fakerFormatters as $formatter): ?>
                        <option value="<?= e($formatter) ?>" <?= (isset($formModel->mappings[$column]) && $formModel->mappings[$column] == $formatter) ? 'selected' : '' ?>>
                            <?= e($formatter) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
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