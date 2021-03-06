<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Nicolaslopezj\Searchable\SearchableTrait;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Traits\Eloquent\CommentLike;
use Carbon\Carbon;

class Campaign extends BaseModel
{
    use SoftDeletes, CommentLike, SearchableTrait;
    const BLOCK = 0;
    const ACTIVE = 1;
    const APPROVING = 0;
    const REQUEST_USER = 2;
    const APPROVED = 1;
    const REJECT = 0;
    const FLAG_APPROVE = 'approve';
    const FLAG_REJECT = 'reject';

    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
    }

    protected $fillable = [
        'id',
        'hashtag',
        'title',
        'description',
        'longitude',
        'latitude',
        'status',
        'address',
        'number_of_likes',
        'number_of_comments',
    ];

    protected $dates = ['deleted_at'];

    protected $appends = [
        'slug',
    ];

    protected $searchable = [
        'columns' => [
            'campaigns.hashtag' => 9,
            'tags.name' => 10,
            'campaigns.title' => 11,
        ],

        'joins' => [
            'campaign_tag' => ['campaign_tag.campaign_id', 'campaigns.id'],
            'tags' => ['campaign_tag.tag_id', 'tags.id'],
        ],
    ];

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot(
                'role_id',
                'status',
                'is_manager',
                'deleted_at'
            )
            ->withTimestamps();
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function likes()
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function settings()
    {
        return $this->morphMany(Setting::class, 'settingable');
    }

    public function activities()
    {
        return $this->morphMany(Activity::class, 'activitiable');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class)->withPivot('deleted_at')->withTimestamps();
    }

    public function actions()
    {
        return $this->hasManyThrough(Action::class, Event::class);
    }

    public function donations()
    {
        return $this->hasMany(Donation::class);
    }

    public function getUserByRole($roles)
    {
        $roles = is_array($roles) ? $roles : [$roles];

        $roleIds = Role::whereIn('name', $roles)->pluck('id');

        return $this->users()
            ->wherePivotIn('role_id', $roleIds)
            ->wherePivot('status', static::APPROVED);
    }

    public function owner()
    {
        return $this->getUserByRole('owner');
    }

    public function moderators()
    {
        return $this->getUserByRole('moderator');
    }

    public function members()
    {
        return $this->getUserByRole('member');
    }

    public function activeUsers()
    {
        return $this->getUserByRole(['owner', 'moderator', 'member']);
    }

    public function approvedUsers()
    {
        return $this->users()->wherePivot('status', static::APPROVED);
    }

    public function blockeds()
    {
        return $this->getUserByRole('blocked')->get();
    }

    public function expenses()
    {
        return $this->hasManyThrough(Expense::class, Event::class);
    }

    public function isMember()
    {
        $roleId = Role::where('name', Role::ROLE_MEMBER)->first()->id;

        return $this->users()
            ->wherePivot('role_id', $roleId)
            ->wherePivot('status', static::APPROVED)
            ->wherePivot('user_id', \Auth::guard('api')->user()->id);
    }

    public function isOwner()
    {
        $roleId = Role::where('name', Role::ROLE_OWNER)->first()->id;

        return $this->users()
            ->wherePivot('role_id', $roleId)
            ->wherePivot('status', static::APPROVED)
            ->wherePivot('user_id', \Auth::guard('api')->user()->id);
    }

    public function isActive()
    {
        return $this->attributes['status'] == static::ACTIVE;
    }

    public function getSlugAttribute()
    {
        return str_slug(str_limit($this->title, 100) . ' ' . $this->id);
    }
}
