<?php

namespace App\Services\Pds\Data;

readonly class ParsedPds
{
    /**
     * @param  array<int, CharacterReference>  $characterReferences
     */
    public function __construct(
        public PersonalInformation $personalInformation,
        public FamilyBackground $familyBackground,
        public EducationalBackground $educationalBackground,
        public CivilServiceEligibility $civilServiceEligibility,
        public WorkExperience $workExperience,
        public LearningAndDevelopment $learningAndDevelopment,
        public VoluntaryWork $voluntaryWork,
        public OtherInformation $otherInformation,
        public array $characterReferences,
        public Certification $certification,
    ) {}
}
