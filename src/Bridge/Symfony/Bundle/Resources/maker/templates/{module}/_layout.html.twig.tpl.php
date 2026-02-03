{% extends "base.html.twig" %}

{% import '@_theme/components/breadcrumbs.html.twig' as breadcrumbs %}
{% block breadcrumb %}
    {{ parent() }}
    {{ breadcrumbs.item('breadcrumb.<?= $module ?>'|trans({}, 'messages'), 'folder') }}
{% endblock %}
