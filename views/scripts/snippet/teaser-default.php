<?php
$lightBoxParam = !is_null($this->getParam('useLightBox')) ? $this->getParam('useLightBox') : $this->checkbox('useLightBox')->isChecked();
$useLightBox = $lightBoxParam && !$this->editmode;
$hasLink = !$this->globallink('link')->isEmpty();
?>
<div class="single-teaser default <?= $useLightBox ? 'light-gallery' : ''; ?>">

    <?= $useLightBox ? '<a href="' . $this->image('image')->getThumbnail('lightBoxImage') . '" class="item">' : ($hasLink ? '<a href="' . $this->globallink('link')->getHref() . '" class="item">' : ''); ?>

    <?= $this->image('image', [

        'thumbnail' => 'standardTeaser',
        'class' => 'img-responsive'

    ]) ?>

    <?= $useLightBox || $hasLink ? '</a>' : ''; ?>

    <h3><?= $this->input('headline') ?></h3>

    <div>
        <?= $this->wysiwyg('text', ['height' => 100]); ?>
    </div>

    <p>
        <?= $this->globallink('link', ['class' => 'btn btn-default']); ?>
    </p>

</div>