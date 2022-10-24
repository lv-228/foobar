<?php

/**
 * Преобразует исходный URL по нескольким критериям
 *
 * Преобразует URL по критериям:
 * 1. Удалить параметры запроса равные 3
 * 2. Отсортировать парметры в GET запросе по значению
 * 3. Перенести URL-путь в запрос как 
 * GET параметр со значением url
 * 
 * Если входной параметр не отвечает требованиям URL строки возвращает NULL
 *
 * @param string $url URL адрес содержащий протокол, хост, URL-путь и строку GET параметров
 *
 * @return null|string
 */
function representUrl(string $url): ?string
{
	$url_parts = parse_url($url);
	if(!isset( $url_parts['scheme'], $url_parts['host'], $url_parts['path'], $url_parts['query'])) return null;
	parse_str($url_parts['query'], $url_params);

	array_filter($url_params, function($value, $key) use(&$url_params)
	{
		if($value == 3)
			unset($url_params[$key]);
	}
	,ARRAY_FILTER_USE_BOTH);

	asort($url_params);

	$url_params['url'] = $url_parts['path'];
	$GET_result 	   = http_build_query($url_params);
	
	return $url_parts['scheme'] . '://' . $url_parts['host'] . '/?' . $GET_result;
}

var_dump(representUrl('https://www.somehost.com/test/index.html?param1=4&param2=3&param3=2&param4=1&param5=3'));

