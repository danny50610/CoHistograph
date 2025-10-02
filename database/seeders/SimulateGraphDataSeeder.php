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

    protected VertexType $youtubeVideo;
    protected VertexProperty $youtubeVideoPropertyId;

    protected EdgeType $vocalEdge;
    protected EdgeProperty $vocalEdgePropertyOrder;

    protected EdgeType $hasYoutubeVideoEdge;

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

        $this->youtubeVideo = VertexType::create([
            'name' => 'Youtube 影片',
            'description' => '',
            'age_label_name' => 'youtube_video',
        ]);

        $this->youtubeVideoPropertyId = new VertexProperty([
            'name' => 'Id',
            'description' => '',
            'age_property_name' => 'id',
            'age_property_type' => PropertyType::String,
        ]);
        $this->youtubeVideoPropertyId->vertexType()->associate($this->youtubeVideo);
        $this->youtubeVideoPropertyId->save();

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

        $this->hasYoutubeVideoEdge = new EdgeType([
            'name' => 'Youtube 影片',
            'description' => '',
            'age_label_name' => 'has_youtube_video',
        ]);
        $this->hasYoutubeVideoEdge->startVertex()->associate($this->song);
        $this->hasYoutubeVideoEdge->endVertex()->associate($this->youtubeVideo);
        $this->hasYoutubeVideoEdge->save();
    }

    protected function createGraphData()
    {
        // 星街すいせい - vocal -> 綺麗事 - has_youtube_video -> (https://www.youtube.com/watch?v=VPhLXeU25KA)
        // AZKi - vocal -> いのち(2024 ver.) - has_youtube_video -> (https://www.youtube.com/watch?v=hQ3rYiaUsUY)
        // AZKi × 星街すいせい - vocal -> The Last Frontier - has_youtube_video -> (https://www.youtube.com/watch?v=-9wUbw5qevU)

        $this->createVtuber('星街すいせい');
        $this->createVtuber('AZKi');

        $this->createSong('綺麗事');
        $this->createSong('いのち(2024 ver.)');
        $this->createSong('The Last Frontier');

        $this->createYoutubeVideo('VPhLXeU25KA');
        $this->createYoutubeVideo('hQ3rYiaUsUY');
        $this->createYoutubeVideo('-9wUbw5qevU');

        $this->createVocalEdge('星街すいせい', '綺麗事', 1);
        $this->createVocalEdge('AZKi', 'いのち(2024 ver.)', 1);
        $this->createVocalEdge('AZKi', 'The Last Frontier', 1);
        $this->createVocalEdge('星街すいせい', 'The Last Frontier', 2);

        $this->createHasYoutubeVideoEdge('綺麗事', 'VPhLXeU25KA');
        $this->createHasYoutubeVideoEdge('いのち(2024 ver.)', 'hQ3rYiaUsUY');
        $this->createHasYoutubeVideoEdge('The Last Frontier', '-9wUbw5qevU');
    }

    protected function createVtuber(string $name)
    {
         DB::apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) use ($name) {
            return $builder->createNode(null, $this->vTuber->age_label_name, [
                $this->vTuberPropertyName->age_property_name => $name,
            ])->setAs(['v']);
        })->get();
    }

    protected function createSong(string $name)
    {
         DB::apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) use ($name) {
            return $builder->createNode(null, $this->song->age_label_name, [
                $this->songPropertyName->age_property_name => $name,
            ])->setAs(['v']);
        })->get();
    }

    protected function createYoutubeVideo(string $id)
    {
         DB::apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) use ($id) {
            return $builder->createNode(null, $this->youtubeVideo->age_label_name, [
                $this->youtubeVideoPropertyId->age_property_name => $id,
            ])->setAs(['v']);
        })->get();
    }

    protected function createVocalEdge(string $vtuberName, string $songName, int $order)
    {
        DB::apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) use ($vtuberName, $songName, $order) {
            return $builder
                ->matchNode('a', $this->vTuber->age_label_name, [
                    $this->vTuberPropertyName->age_property_name => $vtuberName,
                ])
                ->matchNode('b', $this->song->age_label_name, [
                    $this->songPropertyName->age_property_name => $songName,
                ])
                ->createNode('a')
                ->withCreateEdge(Direction::RIGHT, null, $this->vocalEdge->age_label_name, [
                    $this->vocalEdgePropertyOrder->age_property_name => $order,
                ])
                ->withCreateNode('b')
                ->setAs(['v']);
        })->get();
    }

    protected function createHasYoutubeVideoEdge(string $songName, string $youtubeVideoId)
    {
        DB::apacheAgeCypher(config('cohistograph.app.graph.name'), function (Builder $builder) use ($songName, $youtubeVideoId) {
            return $builder
                ->matchNode('a', $this->song->age_label_name, [
                    $this->songPropertyName->age_property_name => $songName,
                ])
                ->matchNode('b', $this->youtubeVideo->age_label_name, [
                    $this->youtubeVideoPropertyId->age_property_name => $youtubeVideoId,
                ])
                ->createNode('a')
                ->withCreateEdge(Direction::RIGHT, null, $this->hasYoutubeVideoEdge->age_label_name)
                ->withCreateNode('b')
                ->setAs(['v']);
        })->get();
    }
}
