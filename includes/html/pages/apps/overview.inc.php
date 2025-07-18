<?php

use App\Models\Application;

$graph_array['height'] = '100';
$graph_array['width'] = '218';
$graph_array['to'] = \App\Facades\LibrenmsConfig::get('time.now');
$graph_array['from'] = \App\Facades\LibrenmsConfig::get('time.day');
$graph_array_zoom = $graph_array;
$graph_array_zoom['height'] = '150';
$graph_array_zoom['width'] = '400';
$graph_array['legend'] = 'no';

$index = 0;
foreach (Application::query()->hasAccess(Auth::user())->with('device')->get()->sortBy('show_name', SORT_NATURAL | SORT_FLAG_CASE)->groupBy('app_type') as $type => $groupedApps) {
    echo '<div style="clear: both;">';
    echo $index > 0 ? '<hr />' : '';
    $index++;
    echo '<h4>' . generate_link(htmlentities($groupedApps->first()->displayName()), ['page' => 'apps', 'app' => $type]) . '</h4>';
    /** @var \Illuminate\Support\Collection $groupedApps */
    $groupedApps = $groupedApps->sortBy(function ($app) {
        return $app->device->hostname;
    });
    /** @var Application $app */
    foreach ($groupedApps as $app) {
        $graph_type = $graphs[$app->app_type][0] ?? '';

        $graph_array['type'] = 'application_' . $app->app_type . '_' . $graph_type;
        $graph_array['id'] = $app->app_id;
        $graph_array_zoom['type'] = 'application_' . $app->app_type . '_' . $graph_type;
        $graph_array_zoom['id'] = $app->app_id;

        $overlib_url = route('device', [$app->device_id, 'apps', "app=$app->app_type"]);

        $app_state = \LibreNMS\Util\Html::appStateIcon($app->app_state);
        $app_state_info = '<font color="' . $app_state['color'] . '"><i title="' . $app_state['hover_text'] . '" class="fa ' . $app_state['icon'] . ' fa-fw fa-lg" aria-hidden="true"></i></font>';

        $content_add = '';
        $overlib_link = '<span style="float:left; margin-left: 10px; font-weight: bold;">' . $app_state_info . htmlentities($app->device?->shortDisplayName() ?? '') . '</span>';
        if (! empty($app->app_instance)) {
            $overlib_link .= '<span style="float:right; margin-right: 10px; font-weight: bold;">' . $app->app_instance . '</span>';
            $content_add = '(' . $app->app_instance . ')';
        }

        $overlib_link .= '<br/>';
        $overlib_link .= \LibreNMS\Util\Url::graphTag($graph_array);
        $overlib_content = generate_overlib_content($graph_array, htmlentities($app->device?->shortDisplayName() ?? '') . ' - ' . htmlentities($app->displayName()) . $content_add);

        echo "<div style='display: block; padding: 1px; padding-top: 3px; margin: 2px; min-height:165px; max-height:165px;
                      text-align: center; float: left;'>";
        echo \LibreNMS\Util\Url::overlibLink($overlib_url, $overlib_link, $overlib_content);
        echo '</div>';
    } //end foreach
    echo '</div>';
}//end foreach
