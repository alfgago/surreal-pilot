#include "ContextExporter.h"
#include "Engine/Blueprint.h"
#include "BlueprintGraph/Classes/K2Node.h"
#include "BlueprintGraph/Classes/K2Node_Event.h"
#include "BlueprintGraph/Classes/K2Node_FunctionEntry.h"
#include "BlueprintGraph/Classes/K2Node_FunctionResult.h"
#include "BlueprintGraph/Classes/K2Node_VariableGet.h"
#include "BlueprintGraph/Classes/K2Node_VariableSet.h"
#include "BlueprintGraph/Classes/K2Node_CallFunction.h"
#include "EdGraph/EdGraphPin.h"
#include "EdGraph/EdGraph.h"
#include "Engine/Selection.h"
#include "Editor.h"
#include "LevelEditor.h"
#include "Dom/JsonObject.h"
#include "Serialization/JsonSerializer.h"
#include "Serialization/JsonWriter.h"
#include "Misc/DateTime.h"
#include "HAL/PlatformFilemanager.h"
#include "Misc/FileHelper.h"

void UContextExporter::Initialize(FSubsystemCollectionBase& Collection)
{
    Super::Initialize(Collection);
    UE_LOG(LogTemp, Log, TEXT("SurrealPilot ContextExporter initialized"));
}

void UContextExporter::Deinitialize()
{
    Super::Deinitialize();
    UE_LOG(LogTemp, Log, TEXT("SurrealPilot ContextExporter deinitialized"));
}

UContextExporter* UContextExporter::Get()
{
    if (GEditor)
    {
        return GEditor->GetEditorSubsystem<UContextExporter>();
    }
    return nullptr;
}

FString UContextExporter::ExportBlueprintContext(UBlueprint* Blueprint)
{
    if (!Blueprint)
    {
        UE_LOG(LogTemp, Warning, TEXT("ContextExporter: Blueprint is null"));
        return TEXT("{}");
    }

    TSharedPtr<FJsonObject> ContextJson = MakeShareable(new FJsonObject);
    
    // Basic blueprint information
    ContextJson->SetStringField(TEXT("name"), Blueprint->GetName());
    ContextJson->SetStringField(TEXT("path"), Blueprint->GetPathName());
    ContextJson->SetStringField(TEXT("type"), TEXT("Blueprint"));
    ContextJson->SetStringField(TEXT("timestamp"), FDateTime::Now().ToString());
    
    // Parent class information
    if (Blueprint->ParentClass)
    {
        ContextJson->SetStringField(TEXT("parentClass"), Blueprint->ParentClass->GetName());
    }
    
    // Export variables
    TArray<TSharedPtr<FJsonValue>> VariablesArray = ExportBlueprintVariables(Blueprint);
    ContextJson->SetArrayField(TEXT("variables"), VariablesArray);
    
    // Export functions
    TArray<TSharedPtr<FJsonValue>> FunctionsArray = ExportBlueprintFunctions(Blueprint);
    ContextJson->SetArrayField(TEXT("functions"), FunctionsArray);
    
    // Export graphs
    TArray<TSharedPtr<FJsonValue>> GraphsArray;
    for (UEdGraph* Graph : Blueprint->UbergraphPages)
    {
        if (Graph)
        {
            TSharedPtr<FJsonObject> GraphJson = ExportBlueprintGraph(Graph);
            if (GraphJson.IsValid())
            {
                GraphsArray.Add(MakeShareable(new FJsonValueObject(GraphJson)));
            }
        }
    }
    ContextJson->SetArrayField(TEXT("graphs"), GraphsArray);
    
    return JsonObjectToString(ContextJson);
}

FString UContextExporter::ExportErrorContext(const TArray<FString>& Errors)
{
    TSharedPtr<FJsonObject> ErrorJson = MakeShareable(new FJsonObject);
    
    ErrorJson->SetStringField(TEXT("type"), TEXT("BuildErrors"));
    ErrorJson->SetStringField(TEXT("timestamp"), FDateTime::Now().ToString());
    ErrorJson->SetNumberField(TEXT("errorCount"), Errors.Num());
    
    // Convert errors to JSON array
    TArray<TSharedPtr<FJsonValue>> ErrorsArray;
    for (int32 i = 0; i < Errors.Num(); i++)
    {
        TSharedPtr<FJsonObject> ErrorObj = MakeShareable(new FJsonObject);
        ErrorObj->SetNumberField(TEXT("index"), i);
        ErrorObj->SetStringField(TEXT("message"), Errors[i]);
        
        // Try to parse error components (file, line, severity)
        FString ErrorMessage = Errors[i];
        FString FilePath, LineNumber, Severity, Description;
        
        // Basic error parsing - look for common patterns
        if (ErrorMessage.Contains(TEXT("Error:")))
        {
            Severity = TEXT("Error");
            ErrorMessage.Split(TEXT("Error:"), &FilePath, &Description);
        }
        else if (ErrorMessage.Contains(TEXT("Warning:")))
        {
            Severity = TEXT("Warning");
            ErrorMessage.Split(TEXT("Warning:"), &FilePath, &Description);
        }
        else
        {
            Severity = TEXT("Unknown");
            Description = ErrorMessage;
        }
        
        // Try to extract line number
        if (FilePath.Contains(TEXT("(")))
        {
            FString FileOnly, LineInfo;
            FilePath.Split(TEXT("("), &FileOnly, &LineInfo);
            if (LineInfo.Contains(TEXT(")")))
            {
                LineInfo.Split(TEXT(")"), &LineNumber, nullptr);
                FilePath = FileOnly;
            }
        }
        
        ErrorObj->SetStringField(TEXT("severity"), Severity);
        ErrorObj->SetStringField(TEXT("description"), Description.TrimStartAndEnd());
        if (!FilePath.IsEmpty())
        {
            ErrorObj->SetStringField(TEXT("file"), FilePath.TrimStartAndEnd());
        }
        if (!LineNumber.IsEmpty())
        {
            ErrorObj->SetStringField(TEXT("line"), LineNumber.TrimStartAndEnd());
        }
        
        ErrorsArray.Add(MakeShareable(new FJsonValueObject(ErrorObj)));
    }
    
    ErrorJson->SetArrayField(TEXT("errors"), ErrorsArray);
    
    return JsonObjectToString(ErrorJson);
}

FString UContextExporter::ExportSelectionContext()
{
    TSharedPtr<FJsonObject> SelectionJson = MakeShareable(new FJsonObject);
    
    SelectionJson->SetStringField(TEXT("type"), TEXT("Selection"));
    SelectionJson->SetStringField(TEXT("timestamp"), FDateTime::Now().ToString());
    
    TArray<UObject*> SelectedObjects = GetSelectedObjects();
    SelectionJson->SetNumberField(TEXT("selectionCount"), SelectedObjects.Num());
    
    TArray<TSharedPtr<FJsonValue>> SelectionArray;
    for (UObject* SelectedObject : SelectedObjects)
    {
        if (SelectedObject)
        {
            TSharedPtr<FJsonObject> ObjectJson = MakeShareable(new FJsonObject);
            ObjectJson->SetStringField(TEXT("name"), SelectedObject->GetName());
            ObjectJson->SetStringField(TEXT("class"), SelectedObject->GetClass()->GetName());
            ObjectJson->SetStringField(TEXT("path"), SelectedObject->GetPathName());
            
            // If it's a blueprint node, export additional context
            if (UK2Node* Node = Cast<UK2Node>(SelectedObject))
            {
                TSharedPtr<FJsonObject> NodeJson = ExportNode(Node);
                if (NodeJson.IsValid())
                {
                    ObjectJson->SetObjectField(TEXT("nodeData"), NodeJson);
                }
            }
            
            SelectionArray.Add(MakeShareable(new FJsonValueObject(ObjectJson)));
        }
    }
    
    SelectionJson->SetArrayField(TEXT("selectedObjects"), SelectionArray);
    
    return JsonObjectToString(SelectionJson);
}

TSharedPtr<FJsonObject> UContextExporter::ExportBlueprintGraph(UEdGraph* Graph)
{
    if (!Graph)
    {
        return nullptr;
    }
    
    TSharedPtr<FJsonObject> GraphJson = MakeShareable(new FJsonObject);
    
    GraphJson->SetStringField(TEXT("name"), Graph->GetName());
    GraphJson->SetStringField(TEXT("schema"), Graph->Schema ? Graph->Schema->GetName() : TEXT("Unknown"));
    
    // Export nodes
    TArray<TSharedPtr<FJsonValue>> NodesArray;
    for (UEdGraphNode* GraphNode : Graph->Nodes)
    {
        if (UK2Node* K2Node = Cast<UK2Node>(GraphNode))
        {
            TSharedPtr<FJsonObject> NodeJson = ExportNode(K2Node);
            if (NodeJson.IsValid())
            {
                NodesArray.Add(MakeShareable(new FJsonValueObject(NodeJson)));
            }
        }
    }
    
    GraphJson->SetArrayField(TEXT("nodes"), NodesArray);
    GraphJson->SetNumberField(TEXT("nodeCount"), NodesArray.Num());
    
    return GraphJson;
}

TSharedPtr<FJsonObject> UContextExporter::ExportNode(UK2Node* Node)
{
    if (!Node)
    {
        return nullptr;
    }
    
    TSharedPtr<FJsonObject> NodeJson = MakeShareable(new FJsonObject);
    
    NodeJson->SetStringField(TEXT("name"), Node->GetName());
    NodeJson->SetStringField(TEXT("class"), Node->GetClass()->GetName());
    NodeJson->SetStringField(TEXT("title"), Node->GetNodeTitle(ENodeTitleType::FullTitle).ToString());
    NodeJson->SetStringField(TEXT("tooltip"), Node->GetTooltipText().ToString());
    
    // Node position
    NodeJson->SetNumberField(TEXT("posX"), Node->NodePosX);
    NodeJson->SetNumberField(TEXT("posY"), Node->NodePosY);
    
    // Export pins
    TArray<TSharedPtr<FJsonValue>> PinsArray = ExportNodePins(Node);
    NodeJson->SetArrayField(TEXT("pins"), PinsArray);
    
    // Special handling for different node types
    if (UK2Node_CallFunction* FunctionNode = Cast<UK2Node_CallFunction>(Node))
    {
        if (FunctionNode->GetTargetFunction())
        {
            NodeJson->SetStringField(TEXT("functionName"), FunctionNode->GetTargetFunction()->GetName());
        }
    }
    else if (UK2Node_VariableGet* VarGetNode = Cast<UK2Node_VariableGet>(Node))
    {
        NodeJson->SetStringField(TEXT("variableName"), VarGetNode->GetVarName().ToString());
    }
    else if (UK2Node_VariableSet* VarSetNode = Cast<UK2Node_VariableSet>(Node))
    {
        NodeJson->SetStringField(TEXT("variableName"), VarSetNode->GetVarName().ToString());
    }
    else if (UK2Node_Event* EventNode = Cast<UK2Node_Event>(Node))
    {
        NodeJson->SetStringField(TEXT("eventName"), EventNode->GetFunctionName().ToString());
    }
    
    return NodeJson;
}

TArray<TSharedPtr<FJsonValue>> UContextExporter::ExportNodePins(UK2Node* Node)
{
    TArray<TSharedPtr<FJsonValue>> PinsArray;
    
    if (!Node)
    {
        return PinsArray;
    }
    
    for (UEdGraphPin* Pin : Node->Pins)
    {
        if (Pin)
        {
            TSharedPtr<FJsonObject> PinJson = MakeShareable(new FJsonObject);
            
            PinJson->SetStringField(TEXT("name"), Pin->PinName.ToString());
            PinJson->SetStringField(TEXT("type"), Pin->PinType.PinCategory.ToString());
            PinJson->SetStringField(TEXT("direction"), Pin->Direction == EGPD_Input ? TEXT("Input") : TEXT("Output"));
            PinJson->SetStringField(TEXT("defaultValue"), Pin->DefaultValue);
            PinJson->SetBoolField(TEXT("isConnected"), Pin->LinkedTo.Num() > 0);
            PinJson->SetNumberField(TEXT("connectionCount"), Pin->LinkedTo.Num());
            
            // Pin subtype information
            if (Pin->PinType.PinSubCategoryObject.IsValid())
            {
                PinJson->SetStringField(TEXT("subType"), Pin->PinType.PinSubCategoryObject->GetName());
            }
            
            PinsArray.Add(MakeShareable(new FJsonValueObject(PinJson)));
        }
    }
    
    return PinsArray;
}

TArray<TSharedPtr<FJsonValue>> UContextExporter::ExportBlueprintVariables(UBlueprint* Blueprint)
{
    TArray<TSharedPtr<FJsonValue>> VariablesArray;
    
    if (!Blueprint)
    {
        return VariablesArray;
    }
    
    for (const FBPVariableDescription& Variable : Blueprint->NewVariables)
    {
        TSharedPtr<FJsonObject> VarJson = MakeShareable(new FJsonObject);
        
        VarJson->SetStringField(TEXT("name"), Variable.VarName.ToString());
        VarJson->SetStringField(TEXT("type"), Variable.VarType.PinCategory.ToString());
        VarJson->SetStringField(TEXT("defaultValue"), Variable.DefaultValue);
        VarJson->SetBoolField(TEXT("isArray"), Variable.VarType.IsArray());
        VarJson->SetBoolField(TEXT("isReference"), Variable.VarType.bIsReference);
        
        // Variable metadata
        if (Variable.VarType.PinSubCategoryObject.IsValid())
        {
            VarJson->SetStringField(TEXT("subType"), Variable.VarType.PinSubCategoryObject->GetName());
        }
        
        VariablesArray.Add(MakeShareable(new FJsonValueObject(VarJson)));
    }
    
    return VariablesArray;
}

TArray<TSharedPtr<FJsonValue>> UContextExporter::ExportBlueprintFunctions(UBlueprint* Blueprint)
{
    TArray<TSharedPtr<FJsonValue>> FunctionsArray;
    
    if (!Blueprint)
    {
        return FunctionsArray;
    }
    
    for (UEdGraph* FunctionGraph : Blueprint->FunctionGraphs)
    {
        if (FunctionGraph)
        {
            TSharedPtr<FJsonObject> FuncJson = MakeShareable(new FJsonObject);
            
            FuncJson->SetStringField(TEXT("name"), FunctionGraph->GetName());
            FuncJson->SetStringField(TEXT("type"), TEXT("Function"));
            
            // Find function entry and result nodes for parameter information
            for (UEdGraphNode* GraphNode : FunctionGraph->Nodes)
            {
                if (UK2Node_FunctionEntry* EntryNode = Cast<UK2Node_FunctionEntry>(GraphNode))
                {
                    TArray<TSharedPtr<FJsonValue>> ParamsArray = ExportNodePins(EntryNode);
                    FuncJson->SetArrayField(TEXT("parameters"), ParamsArray);
                }
                else if (UK2Node_FunctionResult* ResultNode = Cast<UK2Node_FunctionResult>(GraphNode))
                {
                    TArray<TSharedPtr<FJsonValue>> ReturnsArray = ExportNodePins(ResultNode);
                    FuncJson->SetArrayField(TEXT("returns"), ReturnsArray);
                }
            }
            
            FunctionsArray.Add(MakeShareable(new FJsonValueObject(FuncJson)));
        }
    }
    
    return FunctionsArray;
}

TArray<UObject*> UContextExporter::GetSelectedObjects()
{
    TArray<UObject*> SelectedObjects;
    
    if (GEditor)
    {
        USelection* Selection = GEditor->GetSelectedObjects();
        if (Selection)
        {
            for (FSelectionIterator It(*Selection); It; ++It)
            {
                if (UObject* SelectedObject = *It)
                {
                    SelectedObjects.Add(SelectedObject);
                }
            }
        }
        
        // Also check for selected actors
        USelection* ActorSelection = GEditor->GetSelectedActors();
        if (ActorSelection)
        {
            for (FSelectionIterator It(*ActorSelection); It; ++It)
            {
                if (UObject* SelectedActor = *It)
                {
                    SelectedObjects.Add(SelectedActor);
                }
            }
        }
    }
    
    return SelectedObjects;
}

FString UContextExporter::JsonObjectToString(TSharedPtr<FJsonObject> JsonObject)
{
    if (!JsonObject.IsValid())
    {
        return TEXT("{}");
    }
    
    FString OutputString;
    TSharedRef<TJsonWriter<>> Writer = TJsonWriterFactory<>::Create(&OutputString);
    FJsonSerializer::Serialize(JsonObject.ToSharedRef(), Writer);
    
    return OutputString;
}