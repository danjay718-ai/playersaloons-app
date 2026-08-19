<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Support;

final class DefaultTournamentRules
{
    public static function html(): string
    {
        return <<<'HTML'
<h2>PlayerSaloons General Tournament &amp; Head-to-Head Competition Rules</h2>
<p>These rules govern tournaments and head-to-head competitions hosted on PlayerSaloons.com. By registering for or participating in a competition, every participant agrees to follow these rules, the competition listing, applicable game rules, and all PlayerSaloons policies.</p>

<h3>1. Eligibility</h3>
<ul>
<li>Participants in reward-based competitions must be at least 18 years old and legally eligible to participate in their location.</li>
<li>Each participant must have a valid PlayerSaloons account, a legitimate game account in good standing, and authorized access to the listed game and platform.</li>
<li>Participants must comply with the applicable game's and platform's terms of service.</li>
<li>PlayerSaloons may require identity, age, location, or account verification before participation or prize release.</li>
</ul>

<h3>2. Registration &amp; Participation</h3>
<ul>
<li>Registration and all required participation steps must be completed through PlayerSaloons before the published deadline.</li>
<li>Any entry fee, website-credit requirement, team size, participant limit, and eligibility condition will be shown in the competition listing.</li>
<li>Entry fees or credits are refundable only when allowed by the published cancellation policy, when PlayerSaloons cancels the event, or when required by applicable law.</li>
<li>Players may receive a BYE when required by bracket size or competition structure.</li>
<li>If participation is insufficient, PlayerSaloons may cancel, reschedule, pair available players, or reasonably restructure the competition. Any resulting prize or format adjustment will be communicated through the platform.</li>
</ul>

<h3>3. Match Start, Check-In &amp; Timing</h3>
<ul>
<li>Registration, check-in, match, round, and start windows are the dates and times displayed in the competition listing.</li>
<li>Participants must be ready within the listed or system-configured grace period. A no-show or failure to check in may result in automatic forfeiture.</li>
<li>Withdrawal and any refund are governed by the competition status and PlayerSaloons cancellation policy at the time of withdrawal.</li>
</ul>

<h3>4. How to Play</h3>
<ul>
<li>Add, invite, or join the assigned opponent or lobby using the account details registered on PlayerSaloons.</li>
<li>Confirm the game mode, map or track, region, platform, team setup, and listed competition settings before meaningful gameplay begins.</li>
<li>Begin only when the required participants are present and the match is ready under the published schedule.</li>
</ul>

<h3>5. Game Setup &amp; Match Format</h3>
<ul>
<li>The competition type, platform, format, team size, scoring method, and win conditions are those shown in the listing.</li>
<li>Standard competitive settings apply unless the listing provides different approved settings.</li>
<li>Unauthorized modifications, custom software, prohibited controllers, scripts, macros, exploits, or settings are not permitted.</li>
</ul>

<h3>6. Game-Specific Rules</h3>
<p>Any game-specific mode, map, track, character, loadout, assist, server, region, scoring, or restriction stated in the competition listing forms part of these rules. If a game-specific instruction conflicts with this general template, the published competition instruction controls only for that gameplay setting; PlayerSaloons fair-play and conduct rules always remain in force.</p>

<h3>7. Connection, Settings &amp; Technical Complaints</h3>
<ul>
<li>Participants are responsible for checking their internet connection, equipment, account access, and match settings before play begins.</li>
<li>Setting or connection complaints should be raised before meaningful gameplay. Later complaints require clear evidence of a material error or disruption.</li>
<li>Participants are strongly encouraged to use a stable connection and retain screenshots or recordings.</li>
</ul>

<h3>8. Disconnections</h3>
<ul>
<li>A disconnect before meaningful gameplay may require a restart when reasonably possible.</li>
<li>After gameplay begins, an administrator may order a replay, continuation, or ruling based on match progress, available evidence, game limitations, and fairness.</li>
<li>Repeated or intentional disconnections may result in forfeiture, disqualification, or account action.</li>
</ul>

<h3>9. Result Reporting &amp; Verification</h3>
<ul>
<li>Results must be submitted through PlayerSaloons promptly after completion.</li>
<li>Opponents must confirm or dispute a submitted result within the response window shown by the platform. Failure to respond may allow the submitted result to be accepted.</li>
<li>Participants must retain evidence showing the final result, relevant player IDs, and any disconnect or disputed event.</li>
<li>PlayerSaloons may review, correct, reject, or reverse a result when reliable evidence or platform records justify it.</li>
</ul>

<h3>10. Cheating, Exploitation &amp; Fair Play</h3>
<p>Cheats, hacks, scripts, macros, unauthorized software, bug exploitation, intentional disconnects, account sharing, collusion, match manipulation, false reporting, deliberate gameplay sabotage, and any attempt to obtain an unfair advantage are prohibited. Violations may result in match loss, disqualification, prize cancellation, suspension, or permanent account restriction.</p>

<h3>11. Tournament Rules</h3>
<ul>
<li>Tournaments may use single elimination, round robin, league, group, championship, or another format stated in the listing.</li>
<li>Brackets and groups may be randomized or seeded using rankings, prior performance, or published competition criteria.</li>
<li>PlayerSaloons may adjust brackets, groups, schedules, or pairings when reasonably necessary because of BYEs, no-shows, technical issues, or insufficient participation.</li>
</ul>

<h3>12. Head-to-Head Competition Rules</h3>
<ul>
<li>A head-to-head competition is a platform-hosted skill competition between eligible participants under the conditions shown in its listing.</li>
<li>Both participants must accept the listed game, platform, entry, prize, schedule, and match conditions before play.</li>
<li>A participant who accepts but does not appear within the allowed grace period may forfeit automatically.</li>
<li>The confirmed winner receives the listed competition award subject to result verification, fraud review, compliance checks, and these rules.</li>
</ul>

<h3>13. Prizes &amp; Rewards</h3>
<ul>
<li>The competition listing states the available prize or reward and any placement allocation.</li>
<li>Prizes remain subject to result verification, fraud prevention, compliance review, and any eligibility requirement.</li>
<li>PlayerSaloons may correct or adjust a prize affected by cancellation, insufficient participation, restructuring, a display error, or another legitimate operational issue, with notice where reasonably practicable.</li>
</ul>

<h3>14. Code of Conduct</h3>
<p>Participants must treat opponents and staff with respect, compete honestly, follow administrator instructions, and refrain from harassment, threats, abusive language, discrimination, or intimidation. Serious or repeated violations may result in removal from the competition or platform.</p>

<h3>15. Disputes</h3>
<ul>
<li>Disputes must be submitted through the available PlayerSaloons process as soon as reasonably possible and within any deadline shown by the platform.</li>
<li>A dispute should include screenshots, video, match identifiers, timestamps, and a clear description of the issue.</li>
<li>PlayerSaloons administrators have final authority over competition rulings, subject to applicable law and published platform policies.</li>
</ul>

<h3>16. Rule Modifications</h3>
<p>PlayerSaloons may update these rules or competition-specific instructions when reasonably necessary. Material changes will be published or communicated before enforcement whenever practicable.</p>

<h3>17. Technical and Liability Notice</h3>
<p>Online competitions may be affected by game errors, server problems, network failures, platform outages, or other events outside PlayerSaloons' reasonable control. PlayerSaloons will apply these rules and available evidence to reach a fair operational decision, but participation remains subject to the limitations stated in the platform terms and applicable law.</p>

<h3>18. Contact</h3>
<p>Questions and support requests may be sent to support@playersaloons.com or submitted through the PlayerSaloons contact page.</p>
HTML;
    }
}
