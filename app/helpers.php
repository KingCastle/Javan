<?php

use Carbon\Carbon;

function flash($title = NULL, $message = NULL)
{
	$flash = app('Javan\Http\Flash');

	if (func_num_args() == 0) {
		return $flash;
	}

	return $flash->info($title, $message);
}

function active($path, $active = 'active')
{
	return Request::is($path) ? $active : '';
}

function select_times_of_day()
{
	$open_time  = strtotime('12:00');
	$close_time = strtotime('23:00');
	$interval   = 15 * 60;
	$output     = '';

	for ($i = $open_time; $i < $close_time; $i += $interval) {
		$output .= '<option ';
		if (time() < $open_time) {
			$output .= 'selected';
		}
		$output .= ' value="' . date('H:i', $i) . '">' . date('H:i', $i) . '</option>';
	}

	return $output;
}

function expired($date)
{
	return $date->lt(Carbon::today());
}

function today($date)
{
	return $date->eq(Carbon::today());
}

function javan_is_open()
{
	$opening_time = (new DateTime('Europe/London'))->setTime(12, 0)->getTimestamp();
	$closing_time = (new DateTime('Europe/London'))->setTime(23, 0)->getTimestamp();

	return time() >= $opening_time && time() <= $closing_time;
}

function less_than_minimum_order()
{
	return \Cart::total() < 20;
}

function sort_column_by($column, $body)
{

	$direction = (request()->get('direction') == 'ASC') ? 'DESC' : 'ASC';

	$route = route(request()->route()->getAction()['as'], ['sortBy' => $column, 'direction' => $direction]);

	return '<a href=' . $route . '>' . $body . '</a>';
}

function persian($string)
{
	$western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
	$eastern = ['۰', '١', '٢', '٣', '۴', '۵', '۶', '۷', '۸', '۹'];

	return str_replace($western, $eastern, $string);
}

function route_parameter($parameter, $slug)
{
	return request()->route()->parameter($parameter) === $slug;
}

function status($model)
{
	if ($model->recent()) {
		return '<span class="badge">NEW</span>';
	}

	if ($model->uptodate()) {
		return '<span class="badge">UPDATED</span>';
	}
}