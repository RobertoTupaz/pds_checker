<?php

namespace App\Services\Pds\Data;

readonly class OtherInformation
{
    /**
     * @param  array<int, string>  $specialSkillsHobbies
     * @param  array<int, string>  $nonAcademicDistinctions
     * @param  array<int, string>  $membershipInAssociations
     */
    public function __construct(
        public array $specialSkillsHobbies,
        public array $nonAcademicDistinctions,
        public array $membershipInAssociations,
        public YesNoAnswer $relatedWithinThirdDegree,
        public YesNoAnswer $relatedWithinFourthDegree,
        public YesNoAnswer $foundGuiltyOfAdministrativeOffense,
        public YesNoAnswer $criminallyCharged,
        public ?string $criminalCaseDateFiled,
        public ?string $criminalCaseStatus,
        public YesNoAnswer $convicted,
        public YesNoAnswer $separatedFromService,
        public YesNoAnswer $candidateInElection,
        public YesNoAnswer $resignedToCampaign,
        public YesNoAnswer $immigrantOrPermanentResident,
        public YesNoAnswer $indigenousGroupMember,
        public YesNoAnswer $personWithDisability,
        public YesNoAnswer $soloParent,
    ) {}
}
