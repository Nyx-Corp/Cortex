{# template route : <?php echo $module; ?>/<?php echo $model; ?>/ #}

{% trans_default_domain '<?php echo $module; ?>' %}

{% extends "<?php echo $module; ?>/<?php echo $model; ?>/_layout.html.twig" %}

{% import '@_theme/components/breadcrumbs.html.twig' as breadcrumbs %}
{% block breadcrumb %}
    {{ parent() }}
    {{ breadcrumbs.item('<?php echo $model; ?>.list.title'|trans, null, null, true) }}
{% endblock %}

{% block page_icon %}<i data-lucide="{{ <?php echo $model; ?>_icon }}" class="size-6 text-primary"></i>{% endblock %}
{% block page_title %}{{ '<?php echo $model; ?>.title'|trans }}{% endblock %}

{% block page_content %}
    {#
        Options list.html.twig:
        - trans_default_domain: Domain de traduction
        - hide_filters: true pour masquer le panneau de filtres
    #}
    {% embed '@_theme/layout/list.html.twig' with { trans_default_domain: '<?php echo $module; ?>', hide_filters: false }%}

        {% block create_link path('<?php echo $module; ?>/<?php echo $model; ?>/create') %}

        {#
            Define explicit columns with _th (header) and _td (cell) blocks.
            The `item` variable contains the current model instance.

            Example:
            {% block firstname_th '<?php echo $model; ?>.fields.firstname.label'|trans %}
            {% block firstname_td %}{{ item.firstname }}{% endblock %}

            {% block lastname_th '<?php echo $model; ?>.fields.lastname.label'|trans %}
            {% block lastname_td %}{{ item.lastname }}{% endblock %}
        #}
        {% block <?php echo $model; ?>_th '<?php echo $Model; ?>'|trans %}
        {% block <?php echo $model; ?>_td %}{{ item }}{% endblock %}

        {% block empty_text '<?php echo $model; ?>.list.empty'|trans %}

    {% endembed %}
{% endblock %}
