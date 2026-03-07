{# template route : <?php echo $module; ?>/<?php echo $model; ?>/<?php echo $action; ?> #}

{% trans_default_domain '<?php echo $module; ?>' %}

{% set title = '<?php echo $model; ?>.<?php echo $action; ?>.title'|trans %}

{% set breadcrumbs = [{ 'label': title }]|merge(breadcrumbs|default([])) %}

{% extends "<?php echo $module; ?>/<?php echo $model; ?>/_layout.html.twig" %}

{% use '@_theme/components/forms.html.twig' %}

{% form_theme form _self %}
{#
    Block overrides priority for form theming :
        > {% block _<?php echo $action; ?>_<fieldName>_widget %}{% endblock %}
        > {% block <fieldType>_<fieldName>_widget %}{% endblock %}}
        > {% block <fieldName>_widget %}{% endblock %}
        > {% block <fieldType>_widget %}{% endblock %}
        > {% block form_widget %}{% endblock %}

    Idem : *_row / *_label /*_errors
#}

{% block main %}
<div class="flex flex-col gap-6 max-h-[calc(100vh-8rem)] overflow-hidden">

    <h1>{{ title }}</h1>
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-foreground">{{ title }}</h1>
        <a href="{{ path('<?php echo $module; ?>/<?php echo $model; ?>/create') }}"
            class="inline-flex items-center gap-2 rounded-md bg-primary hover:bg-primary/90 text-white text-sm font-medium px-4 py-2 cursor-pointer">
            <i data-lucide="plus" class="size-4"></i>
            {{ 'admin.action.create'|trans({}, 'admin') }}
        </a>
    </div>

    {{ form_start(form, { 'attr': { 'class': 'form-horizontal' } }) }}
        {{ form_widget(form) }}
        <button type="submit" class="btn btn-primary">{{ 'form.submit'|trans }}</button>
    {{ form_end(form) }}
</div>
{% endblock %}
