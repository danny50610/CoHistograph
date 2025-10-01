<?php

namespace Database\Seeders;

use App\Enums\PropertyType;
use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Models\VertexProperty;
use App\Models\VertexType;
use Danny50610\LaravelApacheAgeDriver\Enums\Direction;
use Danny50610\LaravelApacheAgeDriver\Query\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SimulateGraphDataSeeder extends Seeder
{
    protected VertexType $vTuber;
    protected VertexProperty $vTuberPropertyName;
    protected VertexType $song;
    protected VertexProperty $songPropertyName;
    protected VertexProperty $songPropertyYoutubeVideoId;
    protected EdgeType $vocalEdge;
    protected EdgeProperty $vocalEdgePropertyOrder;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            DB::statement('SET SESSION search_path = ag_catalog, public;');
            $this->createGraphSchema();
            $this->createGraphData();
        });
    }

    protected function createGraphSchema()
    {
        $this->vTuber = VertexType::create([
            'name' => 'VTuber',
            'description' => 'Virtual YouTuber',
            'age_label_name' => 'vtuber',
        ]);
        $this->vTuberPropertyName = new VertexProperty([
            'name' => '名字',
            'description' => '名字',
            'age_property_name' => 'name',
            'age_property_type' => PropertyType::String,
        ]);
        $this->vTuberPropertyName->vertexType()->associate($this->vTuber);
        $this->vTuberPropertyName->save();

        $this->song = VertexType::create([
            'name' => '歌曲',
            'description' => '',
            'age_label_name' => 'song',
        ]);
        $this->songPropertyName = new VertexProperty([
            'name' => '名稱',
            'description' => '名稱',
            'age_property_name' => 'name',
            'age_property_type' => PropertyType::String,
        ]);
        $this->songPropertyName->vertexType()->associate($this->song);
        $this->songPropertyName->save();

        $this->songPropertyYoutubeVideoId = new VertexProperty([
            'name' => 'Youtube Video MV Id',
            'description' => '',
            'age_property_name' => 'youtube_video_id_mv',
            'age_property_type' => PropertyType::String,
        ]);
        $this->songPropertyYoutubeVideoId->vertexType()->associate($this->song);
        $this->songPropertyYoutubeVideoId->save();

        $this->vocalEdge = new EdgeType([
            'name' => '主唱',
            'description' => '',
            'age_label_name' => 'vocal',
        ]);
        $this->vocalEdge->startVertex()->associate($this->vTuber);
        $this->vocalEdge->endVertex()->associate($this->song);
        $this->vocalEdge->save();

        $this->vocalEdgePropertyOrder = new EdgeProperty([
            'name' => '顯示順序',
            'description' => '',
            'age_property_name' => 'order',
            'age_property_type' => PropertyType::Integer,
        ]);
        $this->vocalEdgePropertyOrder->edgeType()->associate($this->vocalEdge);
        $this->vocalEdgePropertyOrder->save();
    }

    protected function createGraphData()
    {
        // 星街すいせい - vocal -> 綺麗事 (https://www.youtube.com/watch?v=VPhLXeU25KA)
        // AZKi - vocal -> いのち(2024 ver.) (https://www.youtube.com/watch?v=hQ3rYiaUsUY)
        // AZKi × 星街すいせい - vocal -> The Last Frontier (https://www.youtube.com/watch?v=-9wUbw5qevU)

        DB::apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) {
            return $builder->createNode(null, $this->vTuber->age_label_name, [
                $this->vTuberPropertyName->age_property_name => '星街すいせい',
            ])->setAs(['v']);
        })->get();

        DB::apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) {
            return $builder->createNode(null, $this->vTuber->age_label_name, [
                $this->vTuberPropertyName->age_property_name => 'AZKi',
            ])->setAs(['v']);
        })->get();

        DB::apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) {
            return $builder->createNode(null, $this->song->age_label_name, [
                $this->songPropertyName->age_property_name => '綺麗事',
                $this->songPropertyYoutubeVideoId->age_property_name => 'VPhLXeU25KA',
            ])->setAs(['v']);
        })->get();

        DB::apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) {
            return $builder->createNode(null, $this->song->age_label_name, [
                $this->songPropertyName->age_property_name => 'いのち(2024 ver.)',
                $this->songPropertyYoutubeVideoId->age_property_name => 'hQ3rYiaUsUY',
            ])->setAs(['v']);
        })->get();

        DB::apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) {
            return $builder->createNode(null, $this->song->age_label_name, [
                $this->songPropertyName->age_property_name => 'The Last Frontier',
                $this->songPropertyYoutubeVideoId->age_property_name => '-9wUbw5qevU',
            ])->setAs(['v']);
        })->get();

        DB::apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) {
            return $builder
                ->matchNode('a', $this->vTuber->age_label_name, [
                    $this->vTuberPropertyName->age_property_name => '星街すいせい',
                ])
                ->matchNode('b', $this->song->age_label_name, [
                    $this->songPropertyName->age_property_name => '綺麗事',
                ])
                ->createNode('a')
                ->withCreateEdge(Direction::RIGHT, null, $this->vocalEdge->age_label_name, [
                    $this->vocalEdgePropertyOrder->age_property_name => 1,
                ])
                ->withCreateNode('b')
                ->setAs(['v']);
        })->get();

        DB::apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) {
            return $builder
                ->matchNode('a', $this->vTuber->age_label_name, [
                    $this->vTuberPropertyName->age_property_name => 'AZKi',
                ])
                ->matchNode('b', $this->song->age_label_name, [
                    $this->songPropertyName->age_property_name => 'いのち(2024 ver.)',
                ])
                ->createNode('a')
                ->withCreateEdge(Direction::RIGHT, null, $this->vocalEdge->age_label_name, [
                    $this->vocalEdgePropertyOrder->age_property_name => 1,
                ])
                ->withCreateNode('b')
                ->setAs(['v']);
        })->get();

        DB::apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) {
            return $builder
                ->matchNode('a', $this->vTuber->age_label_name, [
                    $this->vTuberPropertyName->age_property_name => 'AZKi',
                ])
                ->matchNode('b', $this->song->age_label_name, [
                    $this->songPropertyName->age_property_name => 'The Last Frontier',
                ])
                ->createNode('a')
                ->withCreateEdge(Direction::RIGHT, null, $this->vocalEdge->age_label_name, [
                    $this->vocalEdgePropertyOrder->age_property_name => 1,
                ])
                ->withCreateNode('b')
                ->setAs(['v']);
        })->get();

        DB::apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) {
            return $builder
                ->matchNode('a', $this->vTuber->age_label_name, [
                    $this->vTuberPropertyName->age_property_name => '星街すいせい',
                ])
                ->matchNode('b', $this->song->age_label_name, [
                    $this->songPropertyName->age_property_name => 'The Last Frontier',
                ])
                ->createNode('a')
                ->withCreateEdge(Direction::RIGHT, null, $this->vocalEdge->age_label_name, [
                    $this->vocalEdgePropertyOrder->age_property_name => 2,
                ])
                ->withCreateNode('b')
                ->setAs(['v']);
        })->get();

    }
}
