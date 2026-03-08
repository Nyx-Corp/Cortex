{# template route : <?php echo $module; ?>/<?php echo $model; ?>/{uuid}/edit #}

{% trans_default_domain '<?php echo $domain; ?>' %}

{% extends "<?php echo $module; ?>/<?php echo $model; ?>/_form.html.twig" %}

{% import '@_theme/components/breadcrumbs.html.twig' as breadcrumbs %}
{% block breadcrumb %}
    {{ parent() }}
    {{ breadcrumbs.item(<?php echo $model; ?>, null, null, true) }}
{% endblock %}

{% block page_title %}
    <i data-lucide="chevron-right" class="size-5 text-zinc-300"></i>
    {{ <?php echo $model; ?> }}
    <i data-lucide="chevron-right" class="size-5 text-zinc-300"></i>
    <span class="text-zinc-400 font-normal">{{ '<?php echo $model; ?>.action.edit'|trans }}</span>
{% endblock %}
