<?php
$settingsModel = $this->formGetModel();
?>

<?= Form::open(['class' => 'layout design-settings']) ?>
    <div class="layout-row">
        <?= $this->formRender() ?>
    </div>

    <div class="form-buttons">
        <div data-control="loader-container">
            <?= Ui::ajaxButton(e(__('Save')), 'onSave')
                ->primary()
                ->ajaxData(['redirect' => false])
                ->hotkey('ctrl+s', 'cmd+s')
                ->loadingMessage(e(__('Saving...'))) ?>

            <?= Ui::ajaxButton(e(__('Generate data')), 'onGenerateData')
                ->warning()
                ->loadingMessage(e(__('Generating...'))) ?>

            <span class="btn-text">
                <span class="button-separator"><?= e(__('or')) ?></span>
                <?= Ui::button(e(__('Cancel')), 'system/settings')
                    ->textLink() ?>
            </span>
        </div>
    </div>
<?= Form::close() ?>