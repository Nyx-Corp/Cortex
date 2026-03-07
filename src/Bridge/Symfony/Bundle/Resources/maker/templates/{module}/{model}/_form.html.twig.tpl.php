{# template route : <?php echo $module; ?>/<?php echo $model; ?>/{action} #}

{% trans_default_domain '<?php echo $domain; ?>' %}

{% extends "<?php echo $module; ?>/<?php echo $model; ?>/_layout.html.twig" %}

{% use '@_theme/components/forms.html.twig' %}

{% form_theme form _self %}

{% block form_row %}
    {% set row_attr = { class: '' }|merge(row_attr|default({})) %}
    {% set widget_container_attr = { class: 'mt-1' }|merge(widget_container_attr|default({})) %}
    {{ parent() }}
{% endblock %}

{% block form_label %}
    {% set label_attr = {'class': 'text-sm font-medium text-foreground'} | merge(label_attr|default({})) %}
    {{ parent() }}
{% endblock %}

{% block form_widget_simple %}
    {% set base_classes = 'block w-full rounded-md bg-card px-3 py-1.5 text-sm text-foreground border border-input placeholder:text-muted-foreground placeholder:italic focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary' %}
    {% set attr = attr|default({})|merge({class: (attr.class|default('') ~ ' ' ~ base_classes)|trim}) %}
    {{ parent() }}
{% endblock %}

{% block page_icon %}<i data-lucide="{{ <?php echo $model; ?>_icon }}" class="size-6"></i>{% endblock %}
{% block page_backlink %}{{ path('<?php echo $module; ?>/<?php echo $model; ?>/index') }}{% endblock %}
{% block page_backlink_text %}{{ '<?php echo $model; ?>.title'|trans }}{% endblock %}

{% block page_content %}
    {{ form_start(form, { 'attr': { 'class': 'flex flex-col gap-6' } }) }}

        {# Section principale #}
        <div class="border border-zinc-200 bg-white">
            <div class="px-4 py-3 border-b border-zinc-200 flex items-center gap-3">
                <i data-lucide="{{ <?php echo $model; ?>_icon }}" class="size-5 text-primary"></i>
                <h2 class="font-medium text-zinc-700">{{ '<?php echo $model; ?>.form.section.main'|trans }}</h2>
            </div>
            <div class="p-4 grid grid-cols-2 gap-4">
                {{ form_rest(form) }}
            </div>
        </div>

        {# Actions #}
        <div class="flex items-center justify-end gap-x-4">
            <a href="{{ path('<?php echo $module; ?>/<?php echo $model; ?>/index') }}" class="inline-flex items-center cursor-pointer text-sm/6 font-semibold text-gray-900 gap-2 hover:text-gray-700">
                {{ 'admin.action.cancel'|trans({}, 'admin') }}
            </a>
            <button type="submit" id="submit-btn" class="inline-flex cursor-pointer items-center gap-2 bg-primary/10 border border-primary/30 px-4 py-2 text-sm font-semibold text-primary hover:bg-primary/20 transition-colors">
                <i data-lucide="check" class="size-4"></i>
                {{ 'admin.action.submit'|trans({}, 'admin') }}
            </button>
        </div>

    {{ form_end(form) }}
{% endblock %}
