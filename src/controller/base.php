<?php

class Base
{
    /**
     * Base constructor.
     */
    public function __construct()
    {

    }

    /**
     * @param $input
     * @return string
     */
    protected function fromCamelCaseToUnderscore($input): string
    {
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $input, $matches);
        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = $match === strtoupper($match) ? strtolower($match) : lcfirst($match);
        }
        return implode('_', $ret);
    }
    /**
     * @param $input
     * @return string
     */
    protected function fromUnderscoreToCamelCase($input): string
    {
        $inputParts = explode('_', $input);
        $output = '';
        foreach ($inputParts as $inputPart) {
            $output .= ucfirst($inputPart);
        }
        return $output;
    }

    /**
     * @param array $fields
     * @param bool $limit
     * @param string $group
     * @param bool $ordering
     * @return string
     */
    public function getFilterByPostparams($fields, $limit = false, $group = '', $ordering = true): string
    {
        global $postParams;
        $startIndex = 0;
        $arrayLength = 25;
        $orderColumn = 0;
        $orderDirection = 'ASC';
        if (isset($postParams['start']) && is_numeric($postParams['start'])) {
            $startIndex = (int)Cleanup::sanitizeString($postParams['start']);
        }
        if (isset($postParams['length']) && is_numeric($postParams['length'])) {
            $arrayLength = (int)Cleanup::sanitizeString($postParams['length']);
            if ((int)$postParams['length'] === -1) {
                $arrayLength = 1000000;
            }
        }
        if (isset($postParams['order'][0]['column']) && is_numeric($postParams['order'][0]['column'])) {
            $orderColumn = (int)Cleanup::sanitizeString($postParams['order'][0]['column']);
        }
        if (isset($postParams['order'][0]['dir'])) {
            if (strtolower($postParams['order'][0]['dir']) == 'asc' || strtolower($postParams['order'][0]['dir']) == 'desc') {
                $orderDirection = Cleanup::sanitizeString($postParams['order'][0]['dir']);
            }
        }
        if ($limit !== false) {
            $startIndex = 0;
            $arrayLength = $limit;
        }
        $whereStr = ' WHERE ';
        if (empty($postParams['columns'])){
            return '';
        }

        foreach ($postParams['columns'] as $column) {
            if (in_array(strtolower($column['search']['value']),['j', 'ja', 'y', 'ye', 'yes']) && Helper::isYesNoSearchable($fields[$column['data']])) {
                $whereStr .= $fields[$column['data']] . " > '' AND ";
            } elseif (in_array(strtolower($column['search']['value']),['n', 'ne', 'nei', 'nein', 'n', 'no']) && Helper::isYesNoSearchable($fields[$column['data']])) {
                $whereStr .= $fields[$column['data']] . " = '' AND ";
            } else {
                if ($column['search']['value'] !== '') {
                    $whereStr .= $fields[$column['data']] . " LIKE '%" . $column['search']['value'] . "%' AND ";
                }
            }
        }
        if (!empty($postParams['search']['value'])) {
            $whereStr .= ' ( ';
            $likeParts = [];
            foreach ($fields as $field) {
                $likeParts[] = $field . " LIKE '%" . $postParams['search']['value'] . "%' ";
            }
            $whereStr .= implode(' OR ', $likeParts) . ' )     ';
        }
        $whereStr = substr($whereStr, 0, -5);
        if (strlen($whereStr) < 8) {
            $whereStr = '';
        }
        if (!empty($group)) {
            $groupStr = ' GROUP BY ' . $group . ' ';
        } else {
            $groupStr = '';
        }
        $filter = $whereStr . $groupStr;
        if ($ordering) {
            $filter .= ' ORDER BY ' . $fields[$orderColumn] . ' ' . $orderDirection;
        }
        $filter .= ' LIMIT ' . $arrayLength . ' OFFSET ' . $startIndex;
        // Rewrite if data target is one of: active packages, domain scans, openvas queue
        if (in_array('scanner', $fields, true)
            || in_array('firstscan', $fields, true)
            || in_array('test_package', $fields, true)) {
            $filter = str_replace('WHERE', 'AND', $filter);
        }
        return $filter;
    }
    /**
     * @param $data
     * @return array|string
     */
    public function utf8ize($data)
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = $this->utf8ize($v);
            }
        } else
            if (is_string($data)) {
                return utf8_encode($data);
            }
        return $data;
    }
}