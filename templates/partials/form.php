<?php
/**
 * Renders a form from config/funnel.json -> forms.{id}.
 * Labels, placeholders, help texts and option labels come from lang/*.json -> forms.{id}.fields.{name}.*
 *
 * Usage in a page template:  <?= partial('form', ['id' => 'lead', 'formErrors' => $formErrors ?? [], 'formOld' => $formOld ?? []]) ?>
 *
 * @var string $id
 * @var array  $formErrors
 * @var array  $formOld
 */

use Funnel\Security\SignedToken;

$form = config("forms.{$id}");
if (!is_array($form)) {
    throw new RuntimeException("Form '{$id}' is not defined in config/funnel.json");
}
$errors = $formErrors[$id] ?? [];
$privacy = config('site.privacy_url');
$privacyUrl = (string) (is_array($privacy) ? ($privacy[lang()] ?? reset($privacy)) : $privacy);
$old = $formOld[$id] ?? [];
$k = "forms.{$id}";
$field = static fn (string $name, string $part) => t("{$k}.fields.{$name}.{$part}");
$optional = static fn (string $name, string $part) => \Funnel\Support\I18n::has("{$k}.fields.{$name}.{$part}") ? t("{$k}.fields.{$name}.{$part}") : '';
?>
<form class="funnel-form" method="post" action="<?= e(form_action()) ?>" novalidate
      data-funnel-form="<?= e($id) ?>"
      data-msg-network="<?= e(t('forms.errors.network')) ?>"
      data-msg-required="<?= e(t('forms.errors.required')) ?>"
      data-msg-email="<?= e(t('forms.errors.email')) ?>"
      data-msg-check="<?= e(t('forms.errors.check_fields')) ?>">
    <input type="hidden" name="form_id" value="<?= e($id) ?>">
    <input type="hidden" name="lang" value="<?= e(lang()) ?>">
    <input type="hidden" name="_token" value="<?= e(SignedToken::create(['f' => $id], 'form')) ?>">
    <input type="hidden" name="page_url" value="">
    <div class="hp" aria-hidden="true">
        <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    </div>

    <div class="form-alert" role="alert" <?= isset($errors['_form']) ? '' : 'hidden' ?>><?= e($errors['_form'] ?? '') ?></div>

<?php foreach ($form['fields'] as $f):
    $name = $f['name'];
    $type = $f['type'] ?? 'text';
    $req = !empty($f['required']);
    $fid = "f-{$id}-{$name}";
    $value = $old[$name] ?? '';
    $error = $errors[$name] ?? '';
    $help = $optional($name, 'help');
    $describedBy = trim(($help ? "{$fid}-help " : '') . "{$fid}-error");
    $common = 'id="' . e($fid) . '" name="' . e($name) . '"'
        . ($req ? ' required aria-required="true"' : '')
        . ($error ? ' aria-invalid="true"' : '')
        . ' aria-describedby="' . e($describedBy) . '"'
        . (isset($f['autocomplete']) ? ' autocomplete="' . e($f['autocomplete']) . '"' : '');
    if ($type === 'hidden'): ?>
    <input type="hidden" name="<?= e($name) ?>" value="<?= e($f['value'] ?? '') ?>">
<?php continue; endif; ?>

    <div class="field field-<?= e($type) ?>" data-field="<?= e($name) ?>">
<?php if ($type === 'checkbox'): ?>
        <label class="check">
            <input type="checkbox" value="1" <?= $common ?> <?= $value ? 'checked' : '' ?>>
            <span><?= t("{$k}.fields.{$name}.label", ['privacy_url' => e($privacyUrl)]) /* may contain a link, so not escaped */ ?></span>
        </label>
<?php else: ?>
        <label for="<?= e($fid) ?>"><?= e($field($name, 'label')) ?><?= $req ? '' : ' <span class="optional">' . e(t('forms.optional')) . '</span>' ?></label>
<?php if ($type === 'textarea'): ?>
        <textarea <?= $common ?> rows="4" maxlength="<?= (int) ($f['max'] ?? 5000) ?>" placeholder="<?= e($optional($name, 'placeholder')) ?>"><?= e($value) ?></textarea>
<?php elseif ($type === 'select'): ?>
        <select <?= $common ?>>
            <option value=""><?= e(t('forms.choose')) ?></option>
<?php foreach ($f['options'] ?? [] as $opt): ?>
            <option value="<?= e($opt) ?>" <?= (string) $value === (string) $opt ? 'selected' : '' ?>><?= e(t("{$k}.fields.{$name}.options.{$opt}")) ?></option>
<?php endforeach; ?>
        </select>
<?php elseif ($type === 'radio'): ?>
        <div class="radio-group" role="radiogroup">
<?php foreach ($f['options'] ?? [] as $opt): ?>
            <label class="check"><input type="radio" name="<?= e($name) ?>" value="<?= e($opt) ?>" <?= $req ? 'required' : '' ?> <?= (string) $value === (string) $opt ? 'checked' : '' ?>> <span><?= e(t("{$k}.fields.{$name}.options.{$opt}")) ?></span></label>
<?php endforeach; ?>
        </div>
<?php else: ?>
        <input type="<?= e($type) ?>" <?= $common ?> value="<?= e($value) ?>"
               <?= isset($f['max']) ? 'maxlength="' . (int) $f['max'] . '"' : '' ?>
               placeholder="<?= e($optional($name, 'placeholder')) ?>">
<?php endif; ?>
<?php endif; ?>
<?php if ($help): ?>
        <p class="help" id="<?= e($fid) ?>-help"><?= e($help) ?></p>
<?php endif; ?>
        <p class="error" id="<?= e($fid) ?>-error" <?= $error ? '' : 'hidden' ?>><?= e($error) ?></p>
    </div>
<?php endforeach; ?>

    <!-- Filled by funnel.js on submit (UTM / click ids / referrer / cookies) -->
<?php foreach (array_merge(config('tracking.attribution_params', []), ['landing_page', 'referrer', 'fbc', 'fbp', 'ga_client_id', 'hubspotutk']) as $param): ?>
    <input type="hidden" name="attribution[<?= e($param) ?>]" value="" data-attr="<?= e($param) ?>">
<?php endforeach; ?>

    <button type="submit" class="btn btn-primary btn-block" data-loading-text="<?= e(t('forms.sending')) ?>"><?= e(t("{$k}.submit")) ?></button>
    <p class="form-note"><?= e(t("{$k}.note")) ?></p>
</form>
