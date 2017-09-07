<?php

namespace Securilex\Authorization;

use PHPUnit\Framework\TestCase;

class SubjectPrefixVoterTest extends TestCase
{
    protected $instance = null;

    protected function setUp()
    {
        $method = new \ReflectionMethod('\Securilex\Authorization\SubjectPrefixVoter',
            'voteOnAttribute');
        $method->setAccessible(true);

        $this->instance = new SubjectPrefixVoter();
    }

    public function testAddSubjectPrefix()
    {
        $this->instance->addSubjectPrefix('prefix', 'role01');
        $this->assertEquals(['role01'],
            $this->instance->getRolesForSubjectPrefix('prefix'));

        $this->instance->addSubjectPrefix('prefix', 'role02');
        $this->assertEquals(['role01', 'role02'],
            $this->instance->getRolesForSubjectPrefix('prefix'));

        $this->instance->addSubjectPrefix('prefix', ['role02', 'role03']);
        $this->assertEquals(['role01', 'role02', 'role03'],
            $this->instance->getRolesForSubjectPrefix('prefix'));
    }

    public function testVoteOnAttribute()
    {
        $this->instance->addSubjectPrefix('prefix01', 'role01');
        $token01 = new \Symfony\Component\Security\Core\Authentication\Token\AnonymousToken('secret',
            'User01', ['role01']);
        $token02 = new \Symfony\Component\Security\Core\Authentication\Token\AnonymousToken('secret',
            'User02', ['role02']);

        $this->assertEquals(SubjectPrefixVoter::ACCESS_GRANTED,
            $this->instance->vote($token01, 'prefix01', ['prefix']));
        $this->assertEquals(SubjectPrefixVoter::ACCESS_DENIED,
            $this->instance->vote($token02, 'prefix01', ['prefix']));
    }
}