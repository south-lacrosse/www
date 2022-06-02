<style>.semla-bb{border-bottom: 1px solid #eee}</style>
<div class="postbox">
    <div class="inside">
        <p>All fixtures data is loaded from a spreadsheet on Google Sheets. This page details the required format of that Sheet.</p>
        <p><b>The spreadsheet must be shared so anyone can view.</b></p>
        <p>The simplest way to understand the format is to visit the
            <a href="https://docs.google.com/spreadsheets/d/1TPAO3-IxTcb5oxOFTRkStNoX4GCGTshJewVriQ69oV0/edit?usp=sharing">Sample Spreadsheet</a>.
            Alternatively <a href="<?= plugins_url('samples/SEMLA_Fixtures.xlsx', dirname(__DIR__,2)) ?>">download an Excel version here</a>.</p>
    </div>
</div>
<div class="postbox">
    <h2 class="semla-bb">Fixtures Spreadsheet Format</h2>
    <div class="inside">
        <p><b>IMPORTANT: There is practically no validation in the programs which update from the Fixtures
            Spreadsheet. Therefore if you mess with the format of the spreadsheet don't be surprised if
            everything breaks!</b></p>
        <p>There must be 5 sheets: Fixtures, Flags, Deductions, Teams, and Divisions.
            In the samples there is also an Instructions sheet on how to fill out the spreadsheet during the season.</p>
        <p><b>Teams</b> lists all teams and their info. The Minimal name is used for column headings in the Fixtures
            Grid, so keep it to 5 or 6 characters. The Short name is used for the mini tables and flags draws
            where long names will overflow the space.</p>
        <p><b>Divisions</b> lists all divisions, the league they are in, and other info, specifically the list of teams competing.
            If you introduce a new division you may need to add a competition for it.</p>
        <p>Leagues separate the Divisions, for example into SEMLA and Local. Tables for each league are displayed on the home page
            in a separate tab, and each league should have separate Tables and Fixtures Grid pages. If you add a new league
            then you should add a Tables and Fixtures Grid page for it, and add the appropriate "SEMLA Data" block to those pages.</p>
        <p>The Teams and Divisions sheets are only needed to set up the season. These sheets
            can be hidden during the season.</p>
        <p><b>Deductions</b> is for any league points deductions.</p>
        <p>The number of points for wins/draws/loses can be set on the <a href="?page=semla_settings">Settings page</a>.</p>
    </div>
</div>
<div class="postbox">
    <h2 class="semla-bb">Fixtures Sheet</h2>
    <div class="inside">
        <p>It is important to keep the columns the same, as the column numbers are hardcoded
            into the programs, so "Competition" must be column C.</p>
        <p><b>Very important:</b> when you load the spreadsheet make sure columns G and I have a Format of "Plain text". This may sound weird,
            but the reason is that we use a specific Google programming interface to read the spreadsheet, and it assumes a column where most
            of the values are numbers is all numeric - and hence it won't return the "R" or "V" values for rearranged/postponed games.
            That's just the way it works.</p>
        <p>You should also do the same for the results columns on the Flags sheet, as these get copied into the Fixtures sheet and would
            cause the same problems.</p>
        <p>"Competition" column: for league matches must be the League and Division, so if the league is "SEMLA"
            and the Division is "Premier Division", then the value must be "SEMLA Premier Division". The programs
            <i>should</i> work for ladders (i.e. Division 1 and 2 teams play each other, with the results counting in
            their respective divisions), but could do with some testing. To do this put both League and Divisions in 
            the Competition column, separated by '/'. For Flags
            matches use the full flags competition with the round name abbreviation, so "Senior Flags R16" or "Minor Flags F".</p>
        <p>It is recommended to leave the Venue column blank unless the game is not at the home team's ground, as if no Venue
            is specified then the Home team is assumed. If you are going to use it then use the full team name. If you put in
            a different name to the Home team, e.g. Home is "Cambridge Eagles" and Venue is "Cambridge Uni", then
            it will be assumed the game is played at a different venue, so on the Fixtures page it will display
            "at Cambridge Uni", which is silly. It is also important to use the full team name as otherwise the pitch
            type cannot be displayed on the Fixtures page for upcoming games.</p>
        <p>Team names must match the full name in the Teams sheet. If team names are changed make sure to "Update everything" to reload
            the teams & league setup.</p>
        <p>When creating the initial fixtures make sure you populate column K ("X") so it will automatically
            make a match count for double points if the Notes column has "double" anywhere in it. The following formula can be
            put in cell K2 and copied down to all rows <code>=IF(AND(REGEXMATCH(G2,"^\d*$"),REGEXMATCH(L2,"(?i)double")),2,"")</code>.</p>
        <p>You will also probably find it useful to have validation on the goals and "v" columns. For column G highlight cells G2 to
            the end of G, select Data->Data validation, change the Criteria to "Custom formula is", and enter <code>=REGEXMATCH(G2,"^([ACPRV]|\d+)$")</code>.
            Similarly for column I the validation formula is <code>=REGEXMATCH(I2,"^([ACPRV]|\d+)$")</code>,
            and H is <code>=REGEXMATCH(H2,"^(v|C|C24)$")</code>.
        <p>If you have recreated the sheet then you might find it useful to add notes to the headers in column H
            <code>v - normal game, C - conceded (score should be 10-0), C24 - conceded within 24 hours (score should be 10-0), Conceding team gets -1 points</code>,
            and in column K ("X") <code>Points multiplier - default 1, 2 for double points games</code>.</p>
        <p>You may have divisions, for example the Local Midlands, where teams play tournaments at various venues,
            but still play each other twice. In this case make sure that for every pairing of teams each team is the Home team once, and
            the Away team once, as otherwise the matches will not display correctly in the Fixtures Grid.</p>
        <p>Once a season is set up, and dates are set up on the Flags sheet, then you can add Flags matches to the main Fixtures sheet with the
            teams and scores automatically added from the Flags sheet. Use the <a href="?page=semla&tab=formulas">Flags Fixtures Formulas tab</a> to get the Formulas to paste.</p>
        <p>Instructions on how to modify this sheet during the season are in the Instructions sheet, and the most important instruction is
            <b>Never delete a row. Once the fixtures are out then games must have a score, be conceded, rearranged, or void.</b>.<p>
    </div>
</div>
<div class="postbox">
    <h2 class="semla-bb">Flags Sheet</h2>
    <div class="inside">
        <p>This sheet has to be in a rigid structure. It must be:</p>
        <ul class="ul-disc">
            <li>Line 1: Competition name in column C</li>
            <li>Line 2: Date of round in column C, and repeated every 5 columns. <b>Every date must have the cell Format of Date</b></li>
            <li>Line 3: Round names in column C, and repeated every 5 columns. Rounds should be 
                "Last x", "Quarter Final", "Semi Final" or "Final" (note: no "s" on the end)</li>
            <li>Grid of matches - see below</li>
            <li>If there are more flags competitions then there must be 2 blank rows, and then repeat as above</li>
        </ul>
        <p>For the grid of matches, the first round should have no gaps between matches, and column B needs H/A
            markers for Home or Away team, or NH and NA for neutral venues, as one team is still
            "home". Column A can have match numbers, but these aren't used.</p>
        <p>The next round should have teams in column H, and H/A in column G, and then repeat across every 5 columns
            until the final. It is very important each match except finals have an H/A, as this is what the
            programs use to determine where a match is. You also need to get the matches in the correct
            order, as the top 2 matches in round 1 are assumed to feed into match 1 in round 2 etc.
            If you have done the draw in a funny way then you will need to unwind the draw so that it is
            in the correct order, so for example if round 2 match 1 is the winners from matches 3 and 5
            from round 1 then you need to put matches 3 and 5 into positions 1 and 2 on the sheet respectively. You
            can put the match number in column A for reference.</p>
        <p>If a team has a bye into round 2 then it must be put into round 1 as one of the teams,
            with the other as Bye. If you need comments then use column E, so for example if the Intermediate
            Flags trickles down into the minor then put "Loser Int R1 Game 7" in column E next to the slot in
            the draw. You can also put it in the team name, but that is not recommended.</p>
    </div>
</div>
