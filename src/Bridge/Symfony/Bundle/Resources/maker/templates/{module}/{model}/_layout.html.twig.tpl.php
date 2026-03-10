{# Override <?php echo $model; ?>_icon to customize the icon for this model #}
{% set <?php echo $model; ?>_icon = <?php echo $model; ?>_icon|default('file') %}
{% set active_menu = '<?php echo $model; ?>.title'|trans({}, '<?php echo $module; ?>') %}

{% extends "<?php echo $module; ?>/_layout.html.twig" %}

{% import '@_theme/components/breadcrumbs.html.twig' as breadcrumbs %}
{% block breadcrumb %}
    {{ parent() }}
    {{ breadcrumbs.item(active_menu, <?php echo $model; ?>_icon, path('<?php echo $module; ?>/<?php echo $model; ?>/index')) }}
{% endblock %}
