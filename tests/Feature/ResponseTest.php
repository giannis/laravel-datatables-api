<?php

namespace GPapakitsos\LaravelDatatables\Tests\Feature;

use GPapakitsos\LaravelDatatables\Tests\FeatureTestCase;
use GPapakitsos\LaravelDatatables\Tests\Models\User as User;

class ResponseTest extends FeatureTestCase
{
    public function testResponseLength()
    {
        $request_data = $this->getRequestDataSample();
        $query_string = http_build_query($request_data);

        $response = $this->get('/'.$this->route_prefix.'/User?'.$query_string);
        $response->assertStatus(200);
        $response->assertJsonCount($request_data['length'], 'data');
    }

    public function testScope()
    {
        $request_data = $this->getRequestDataSample();
        $request_data['scope'] = 'test';
        $query_string = http_build_query($request_data);

        $response = $this->get('/'.$this->route_prefix.'/User?'.$query_string);
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function testScopeArray()
    {
        $request_data = $this->getRequestDataSample();
        $request_data['scope'] = ['byEmail', 'papakitsos_george@yahoo.gr'];
        $query_string = http_build_query($request_data);

        $response = $this->get('/'.$this->route_prefix.'/User?'.$query_string);
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function testExtraWhere()
    {
        $request_data = $this->getRequestDataSample();
        $request_data['extraWhere']['id'] = 1;
        $query_string = http_build_query($request_data);

        $response = $this->get('/'.$this->route_prefix.'/User?'.$query_string);
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function testExtraWhereArray()
    {
        $request_data = $this->getRequestDataSample();
        $request_data['extraWhere']['id'][] = 1;
        $request_data['extraWhere']['id'][] = 2;
        $query_string = http_build_query($request_data);

        $response = $this->get('/'.$this->route_prefix.'/User?'.$query_string);
        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function testSorting()
    {
        $request_data = $this->getRequestDataSample();
        $request_data['order'][0]['column'] = 3;
        $query_string = http_build_query($request_data);

        $response = $this->get('/'.$this->route_prefix.'/User?'.$query_string);
        $response->assertStatus(200);
        $this->assertEquals($this->user->id, $response->getData(true)['data'][0]['id']);
    }

    public function testSortByBelongsToColumn()
    {
        $request_data = $this->getRequestDataSample();
        $request_data['order'][0]['column'] = 5;
        $request_data['order'][0]['dir'] = 'desc';
        $query_string = http_build_query($request_data);

        $response = $this->get('/'.$this->route_prefix.'/User?'.$query_string);
        $response->assertStatus(200);
        $this->assertEquals($this->user->id, $response->getData(true)['data'][0]['id']);
    }

    public function testSortByHasManyColumn()
    {
        $request_data = $this->getRequestDataSample();
        $request_data['order'][0]['column'] = 6;
        $request_data['order'][0]['dir'] = 'desc';
        $query_string = http_build_query($request_data);

        $response = $this->get('/'.$this->route_prefix.'/User?'.$query_string);
        $response->assertStatus(200);
        $this->assertEquals($this->user->id, $response->getData(true)['data'][0]['id']);
    }

    public function testSortByHasOneColumn()
    {
        $request_data = $this->getRequestDataSample();
        $request_data['order'][0]['column'] = 8;
        $request_data['order'][0]['dir'] = 'asc';
        $query_string = http_build_query($request_data);

        $response = $this->get('/'.$this->route_prefix.'/User?'.$query_string);
        $response->assertStatus(200);
        $this->assertEquals(User::orderBy('name')->orderBy('email')->first()->id, $response->getData(true)['data'][0]['id']);
    }

    public function testSearch()
    {
        $request_data = $this->getRequestDataSample();
        $request_data['search']['value'] = 'Papakitsos';
        $query_string = http_build_query($request_data);

        $response = $this->get('/'.$this->route_prefix.'/User?'.$query_string);
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function testSearchByColumn()
    {
        $request_data = $this->getRequestDataSample();
        $request_data['columns'][2]['search']['value'] = 'papakitsos_george@yahoo.gr';
        $query_string = http_build_query($request_data);

        $response = $this->get('/'.$this->route_prefix.'/User?'.$query_string);
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function testSearchByColumnDate()
    {
        $request_data = $this->getRequestDataSample();
        $request_data['columns'][3]['search']['value'] = '23/04/1981'.config('datatables.filters.date_delimiter').'23/04/1981';
        $query_string = http_build_query($request_data);

        $response = $this->get('/'.$this->route_prefix.'/User?'.$query_string);
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function testSearchByColumnJson()
    {
        $request_data = $this->getRequestDataSample();
        $request_data['columns'][7]['search']['value'] = 'PAPAKI';
        $query_string = http_build_query($request_data);

        $response = $this->get('/'.$this->route_prefix.'/User?'.$query_string);
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function testSearchByBelongsToColumn()
    {
        $request_data = $this->getRequestDataSample();
        $request_data['columns'][5]['search']['value'] = $this->country->name;
        $query_string = http_build_query($request_data);

        $response = $this->get('/'.$this->route_prefix.'/User?'.$query_string);
        $response->assertStatus(200);
        $this->assertEquals($this->user->id, $response->getData(true)['data'][0]['id']);
    }

    public function testRelationDateColumnSearch()
    {
        foreach (['15/06/1995', '15-06-1995'] as $searchValue) {
            $request_data = $this->getRequestDataSample();
            $request_data['columns'][5]['search']['value'] = $searchValue;
            $response = $this->get('/'.$this->route_prefix.'/User?'.http_build_query($request_data));
            $response->assertStatus(200);
            $response->assertJsonCount(1, 'data');
            $this->assertEquals($this->user->id, $response->getData(true)['data'][0]['id']);
        }
    }

    public function testSearchByHasOneColumn()
    {
        foreach (['Papakitsos', 'papakitsos_george@yahoo.gr'] as $searchTerm) {
            $request_data = $this->getRequestDataSample();
            $request_data['columns'][8]['search']['value'] = $searchTerm;
            $query_string = http_build_query($request_data);

            $response = $this->get('/'.$this->route_prefix.'/User?'.$query_string);
            $response->assertStatus(200);
            $this->assertEquals($this->user->id, $response->getData(true)['data'][0]['id']);
        }
    }

    public function testSearchByColumnNull()
    {
        $request_data = $this->getRequestDataSample();
        $request_data['columns'][7]['search']['value'] = config('datatables.filters.null_delimiter');
        $query_string = http_build_query($request_data);

        $response = $this->get('/'.$this->route_prefix.'/User?'.$query_string);
        $response->assertStatus(200);
        $this->assertEquals(User::whereNull('settings')->count(), $response->getData(true)['recordsFiltered']);
    }

    public function testSearchByRelationColumnNull()
    {
        $request_data = $this->getRequestDataSample();
        $request_data['columns'][5]['search']['value'] = config('datatables.filters.null_delimiter');
        $query_string = http_build_query($request_data);

        $response = $this->get('/'.$this->route_prefix.'/User?'.$query_string);
        $response->assertStatus(200);
        $this->assertEquals(User::whereNull('country_id')->count(), $response->getData(true)['recordsFiltered']);
    }
}
