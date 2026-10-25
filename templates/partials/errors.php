<?php /** @var string[] $errors */ ?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-error" role="alert">
        <?php foreach ($errors as $error): ?>
            <p><?= e($error) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
