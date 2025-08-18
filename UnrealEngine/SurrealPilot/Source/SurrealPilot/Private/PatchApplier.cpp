#include "PatchApplier.h"
#include "SurrealPilotErrorHandler.h"
#include "Engine/Blueprint.h"
#include "BlueprintGraph/Classes/K2Node.h"
#include "BlueprintGraph/Classes/K2Node_VariableGet.h"
#include "BlueprintGraph/Classes/K2Node_VariableSet.h"
#include "BlueprintGraph/Classes/K2Node_CallFunction.h"
#include "BlueprintGraph/Classes/K2Node_Event.h"
#include "Engine/BlueprintGeneratedClass.h"
#include "EdGraphSchema_K2.h"
#include "KismetCompiler.h"
#include "Editor.h"
#include "AssetRegistry/AssetRegistryModule.h"
#include "Serialization/JsonReader.h"
#include "Serialization/JsonSerializer.h"
#include "Serialization/JsonWriter.h"
#include "Dom/JsonObject.h"

void UPatchApplier::Initialize(FSubsystemCollectionBase& Collection)
{
    Super::Initialize(Collection);
    UE_LOG(LogTemp, Log, TEXT("PatchApplier subsystem initialized"));
}

void UPatchApplier::Deinitialize()
{
    // Cancel any pending transaction
    if (CurrentTransaction.IsValid())
    {
        CancelTransaction();
    }
    
    Super::Deinitialize();
    UE_LOG(LogTemp, Log, TEXT("PatchApplier subsystem deinitialized"));
}

UPatchApplier* UPatchApplier::Get()
{
    if (GEditor)
    {
        return GEditor->GetEditorSubsystem<UPatchApplier>();
    }
    return nullptr;
}

bool UPatchApplier::ApplyJsonPatch(const FString& PatchJson)
{
    LastErrorMessage.Empty();
    
    // Parse the patch JSON
    TArray<TSharedPtr<FJsonObject>> Operations = ParsePatchOperations(PatchJson);
    if (Operations.Num() == 0)
    {
        SetLastError(TEXT("No valid operations found in patch JSON"));
        return false;
    }
    
    // Start transaction for undo support
    BeginTransaction(TEXT("Apply AI Patch"));
    
    bool bAllSucceeded = true;
    
    // Apply each operation
    for (const auto& Operation : Operations)
    {
        if (!ApplyPatchOperation(Operation))
        {
            bAllSucceeded = false;
            UE_LOG(LogTemp, Error, TEXT("Failed to apply patch operation"));
            break;
        }
    }
    
    if (bAllSucceeded)
    {
        EndTransaction();
        UE_LOG(LogTemp, Log, TEXT("Successfully applied patch with %d operations"), Operations.Num());
        FSurrealPilotErrorHandler::ShowUserNotification(
            FString::Printf(TEXT("Successfully applied AI patch with %d operations"), Operations.Num()),
            5.0f, TEXT("Info")
        );
    }
    else
    {
        CancelTransaction();
        FSurrealPilotErrorHandler::HandlePatchError(PatchJson, LastErrorMessage);
    }
    
    return bAllSucceeded;
}

bool UPatchApplier::CanApplyPatch(const FString& PatchJson)
{
    LastErrorMessage.Empty();
    
    // Parse the patch JSON
    TArray<TSharedPtr<FJsonObject>> Operations = ParsePatchOperations(PatchJson);
    if (Operations.Num() == 0)
    {
        SetLastError(TEXT("No valid operations found in patch JSON"));
        return false;
    }
    
    // Validate each operation without applying
    for (const auto& Operation : Operations)
    {
        if (!ValidatePatchOperation(Operation))
        {
            return false;
        }
    }
    
    return true;
}

FString UPatchApplier::GetLastError() const
{
    return LastErrorMessage;
}

TArray<TSharedPtr<FJsonObject>> UPatchApplier::ParsePatchOperations(const FString& PatchJson)
{
    TArray<TSharedPtr<FJsonObject>> Operations;
    
    TSharedPtr<FJsonObject> JsonObject;
    TSharedRef<TJsonReader<>> Reader = TJsonReaderFactory<>::Create(PatchJson);
    
    if (!FJsonSerializer::Deserialize(Reader, JsonObject) || !JsonObject.IsValid())
    {
        SetLastError(TEXT("Invalid JSON format"));
        return Operations;
    }
    
    // Check if it's a single operation or array of operations
    if (JsonObject->HasField(TEXT("operations")))
    {
        const TArray<TSharedPtr<FJsonValue>>* OperationsArray;
        if (JsonObject->TryGetArrayField(TEXT("operations"), OperationsArray))
        {
            for (const auto& OpValue : *OperationsArray)
            {
                TSharedPtr<FJsonObject> OpObject = OpValue->AsObject();
                if (OpObject.IsValid())
                {
                    Operations.Add(OpObject);
                }
            }
        }
    }
    else if (JsonObject->HasField(TEXT("type")))
    {
        // Single operation
        Operations.Add(JsonObject);
    }
    
    return Operations;
}

bool UPatchApplier::ApplyPatchOperation(TSharedPtr<FJsonObject> Operation)
{
    if (!Operation.IsValid())
    {
        SetLastError(TEXT("Invalid operation object"));
        return false;
    }
    
    FString OperationType;
    if (!Operation->TryGetStringField(TEXT("type"), OperationType))
    {
        SetLastError(TEXT("Operation missing 'type' field"));
        return false;
    }
    
    LogPatchOperation(Operation, false);
    
    bool bSuccess = false;
    
    if (OperationType == TEXT("variable_rename"))
    {
        bSuccess = ApplyVariableRename(Operation);
    }
    else if (OperationType == TEXT("node_add"))
    {
        bSuccess = ApplyNodeAddition(Operation);
    }
    else if (OperationType == TEXT("node_modify"))
    {
        bSuccess = ApplyNodeModification(Operation);
    }
    else if (OperationType == TEXT("node_delete"))
    {
        bSuccess = ApplyNodeDeletion(Operation);
    }
    else if (OperationType == TEXT("connection_add") || OperationType == TEXT("connection_remove"))
    {
        bSuccess = ApplyConnectionOperation(Operation);
    }
    else
    {
        SetLastError(FString::Printf(TEXT("Unknown operation type: %s"), *OperationType));
        return false;
    }
    
    LogPatchOperation(Operation, bSuccess);
    return bSuccess;
}

bool UPatchApplier::ValidatePatchOperation(TSharedPtr<FJsonObject> Operation)
{
    if (!Operation.IsValid())
    {
        SetLastError(TEXT("Invalid operation object"));
        return false;
    }
    
    FString OperationType;
    if (!Operation->TryGetStringField(TEXT("type"), OperationType))
    {
        SetLastError(TEXT("Operation missing 'type' field"));
        return false;
    }
    
    FString BlueprintPath;
    if (!Operation->TryGetStringField(TEXT("blueprint"), BlueprintPath))
    {
        SetLastError(TEXT("Operation missing 'blueprint' field"));
        return false;
    }
    
    // Check if blueprint exists
    UBlueprint* Blueprint = FindBlueprint(BlueprintPath);
    if (!Blueprint)
    {
        SetLastError(FString::Printf(TEXT("Blueprint not found: %s"), *BlueprintPath));
        return false;
    }
    
    // Validate operation-specific requirements
    if (OperationType == TEXT("variable_rename"))
    {
        FString OldName, NewName;
        if (!Operation->TryGetStringField(TEXT("old_name"), OldName) ||
            !Operation->TryGetStringField(TEXT("new_name"), NewName))
        {
            SetLastError(TEXT("Variable rename operation missing old_name or new_name"));
            return false;
        }
        
        // Check if old variable exists
        if (!FindVariable(Blueprint, OldName))
        {
            SetLastError(FString::Printf(TEXT("Variable not found: %s"), *OldName));
            return false;
        }
    }
    else if (OperationType == TEXT("node_add"))
    {
        FString NodeType;
        if (!Operation->TryGetStringField(TEXT("node_type"), NodeType))
        {
            SetLastError(TEXT("Node addition operation missing node_type"));
            return false;
        }
    }
    
    return true;
}

bool UPatchApplier::ApplyVariableRename(TSharedPtr<FJsonObject> Operation)
{
    FString BlueprintPath, OldName, NewName;
    
    if (!Operation->TryGetStringField(TEXT("blueprint"), BlueprintPath) ||
        !Operation->TryGetStringField(TEXT("old_name"), OldName) ||
        !Operation->TryGetStringField(TEXT("new_name"), NewName))
    {
        SetLastError(TEXT("Variable rename operation missing required fields"));
        return false;
    }
    
    UBlueprint* Blueprint = FindBlueprint(BlueprintPath);
    if (!Blueprint)
    {
        SetLastError(FString::Printf(TEXT("Blueprint not found: %s"), *BlueprintPath));
        return false;
    }
    
    FBPVariableDescription* Variable = FindVariable(Blueprint, OldName);
    if (!Variable)
    {
        SetLastError(FString::Printf(TEXT("Variable not found: %s"), *OldName));
        return false;
    }
    
    // Rename the variable
    Variable->VarName = FName(*NewName);
    
    // Update all references to this variable in the blueprint graphs
    TArray<UEdGraph*> AllGraphs;
    Blueprint->GetAllGraphs(AllGraphs);
    
    for (UEdGraph* Graph : AllGraphs)
    {
        for (UEdGraphNode* Node : Graph->Nodes)
        {
            if (UK2Node_VariableGet* VarGetNode = Cast<UK2Node_VariableGet>(Node))
            {
                if (VarGetNode->GetVarName() == FName(*OldName))
                {
                    VarGetNode->SetFromFunction(FName(*NewName));
                    VarGetNode->ReconstructNode();
                }
            }
            else if (UK2Node_VariableSet* VarSetNode = Cast<UK2Node_VariableSet>(Node))
            {
                if (VarSetNode->GetVarName() == FName(*OldName))
                {
                    VarSetNode->SetFromFunction(FName(*NewName));
                    VarSetNode->ReconstructNode();
                }
            }
        }
    }
    
    // Mark blueprint as modified
    FBlueprintEditorUtils::MarkBlueprintAsModified(Blueprint);
    
    UE_LOG(LogTemp, Log, TEXT("Renamed variable '%s' to '%s' in blueprint '%s'"), 
           *OldName, *NewName, *BlueprintPath);
    
    return true;
}

bool UPatchApplier::ApplyNodeAddition(TSharedPtr<FJsonObject> Operation)
{
    FString BlueprintPath, NodeType, GraphName;
    
    if (!Operation->TryGetStringField(TEXT("blueprint"), BlueprintPath) ||
        !Operation->TryGetStringField(TEXT("node_type"), NodeType))
    {
        SetLastError(TEXT("Node addition operation missing required fields"));
        return false;
    }
    
    // Graph name is optional, defaults to "EventGraph"
    if (!Operation->TryGetStringField(TEXT("graph"), GraphName))
    {
        GraphName = TEXT("EventGraph");
    }
    
    UBlueprint* Blueprint = FindBlueprint(BlueprintPath);
    if (!Blueprint)
    {
        SetLastError(FString::Printf(TEXT("Blueprint not found: %s"), *BlueprintPath));
        return false;
    }
    
    // Find the target graph
    UEdGraph* TargetGraph = nullptr;
    TArray<UEdGraph*> AllGraphs;
    Blueprint->GetAllGraphs(AllGraphs);
    
    for (UEdGraph* Graph : AllGraphs)
    {
        if (Graph->GetFName() == FName(*GraphName))
        {
            TargetGraph = Graph;
            break;
        }
    }
    
    if (!TargetGraph)
    {
        SetLastError(FString::Printf(TEXT("Graph not found: %s"), *GraphName));
        return false;
    }
    
    // Get position for the new node
    FVector2D NodePosition(0, 0);
    const TSharedPtr<FJsonObject>* PositionObj;
    if (Operation->TryGetObjectField(TEXT("position"), PositionObj))
    {
        double X, Y;
        if ((*PositionObj)->TryGetNumberField(TEXT("x"), X) &&
            (*PositionObj)->TryGetNumberField(TEXT("y"), Y))
        {
            NodePosition = FVector2D(X, Y);
        }
    }
    
    // Create the appropriate node type
    UK2Node* NewNode = nullptr;
    
    if (NodeType == TEXT("VariableGet"))
    {
        FString VariableName;
        if (Operation->TryGetStringField(TEXT("variable_name"), VariableName))
        {
            UK2Node_VariableGet* VarGetNode = NewObject<UK2Node_VariableGet>(TargetGraph);
            VarGetNode->SetFromFunction(FName(*VariableName));
            NewNode = VarGetNode;
        }
    }
    else if (NodeType == TEXT("VariableSet"))
    {
        FString VariableName;
        if (Operation->TryGetStringField(TEXT("variable_name"), VariableName))
        {
            UK2Node_VariableSet* VarSetNode = NewObject<UK2Node_VariableSet>(TargetGraph);
            VarSetNode->SetFromFunction(FName(*VariableName));
            NewNode = VarSetNode;
        }
    }
    else if (NodeType == TEXT("FunctionCall"))
    {
        FString FunctionName;
        if (Operation->TryGetStringField(TEXT("function_name"), FunctionName))
        {
            UK2Node_CallFunction* FuncCallNode = NewObject<UK2Node_CallFunction>(TargetGraph);
            FuncCallNode->SetFromFunction(FName(*FunctionName));
            NewNode = FuncCallNode;
        }
    }
    
    if (!NewNode)
    {
        SetLastError(FString::Printf(TEXT("Failed to create node of type: %s"), *NodeType));
        return false;
    }
    
    // Set node position and add to graph
    NewNode->NodePosX = NodePosition.X;
    NewNode->NodePosY = NodePosition.Y;
    TargetGraph->AddNode(NewNode, true);
    
    // Allocate default pins and reconstruct
    NewNode->AllocateDefaultPins();
    NewNode->ReconstructNode();
    
    // Mark blueprint as modified
    FBlueprintEditorUtils::MarkBlueprintAsModified(Blueprint);
    
    UE_LOG(LogTemp, Log, TEXT("Added node of type '%s' to graph '%s' in blueprint '%s'"), 
           *NodeType, *GraphName, *BlueprintPath);
    
    return true;
}

bool UPatchApplier::ApplyNodeModification(TSharedPtr<FJsonObject> Operation)
{
    // This is a placeholder for node modification
    // Implementation would depend on specific modification types needed
    SetLastError(TEXT("Node modification not yet implemented"));
    return false;
}

bool UPatchApplier::ApplyNodeDeletion(TSharedPtr<FJsonObject> Operation)
{
    FString BlueprintPath, NodeId;
    
    if (!Operation->TryGetStringField(TEXT("blueprint"), BlueprintPath) ||
        !Operation->TryGetStringField(TEXT("node_id"), NodeId))
    {
        SetLastError(TEXT("Node deletion operation missing required fields"));
        return false;
    }
    
    UBlueprint* Blueprint = FindBlueprint(BlueprintPath);
    if (!Blueprint)
    {
        SetLastError(FString::Printf(TEXT("Blueprint not found: %s"), *BlueprintPath));
        return false;
    }
    
    UK2Node* NodeToDelete = FindNode(Blueprint, NodeId);
    if (!NodeToDelete)
    {
        SetLastError(FString::Printf(TEXT("Node not found: %s"), *NodeId));
        return false;
    }
    
    // Remove the node from its graph
    UEdGraph* Graph = NodeToDelete->GetGraph();
    if (Graph)
    {
        Graph->RemoveNode(NodeToDelete);
        
        // Mark blueprint as modified
        FBlueprintEditorUtils::MarkBlueprintAsModified(Blueprint);
        
        UE_LOG(LogTemp, Log, TEXT("Deleted node '%s' from blueprint '%s'"), 
               *NodeId, *BlueprintPath);
        
        return true;
    }
    
    SetLastError(TEXT("Failed to remove node from graph"));
    return false;
}

bool UPatchApplier::ApplyConnectionOperation(TSharedPtr<FJsonObject> Operation)
{
    // This is a placeholder for connection operations
    // Implementation would handle connecting/disconnecting pins between nodes
    SetLastError(TEXT("Connection operations not yet implemented"));
    return false;
}

UBlueprint* UPatchApplier::FindBlueprint(const FString& BlueprintPath)
{
    // Try to find blueprint by asset path
    FAssetRegistryModule& AssetRegistryModule = FModuleManager::LoadModuleChecked<FAssetRegistryModule>("AssetRegistry");
    IAssetRegistry& AssetRegistry = AssetRegistryModule.Get();
    
    FAssetData AssetData = AssetRegistry.GetAssetByObjectPath(FSoftObjectPath(BlueprintPath));
    if (AssetData.IsValid())
    {
        return Cast<UBlueprint>(AssetData.GetAsset());
    }
    
    // Try to find by name in currently loaded blueprints
    for (TObjectIterator<UBlueprint> BlueprintIter; BlueprintIter; ++BlueprintIter)
    {
        UBlueprint* Blueprint = *BlueprintIter;
        if (Blueprint && (Blueprint->GetName() == BlueprintPath || Blueprint->GetPathName() == BlueprintPath))
        {
            return Blueprint;
        }
    }
    
    return nullptr;
}

UK2Node* UPatchApplier::FindNode(UBlueprint* Blueprint, const FString& NodeIdentifier)
{
    if (!Blueprint)
    {
        return nullptr;
    }
    
    TArray<UEdGraph*> AllGraphs;
    Blueprint->GetAllGraphs(AllGraphs);
    
    for (UEdGraph* Graph : AllGraphs)
    {
        for (UEdGraphNode* Node : Graph->Nodes)
        {
            if (UK2Node* K2Node = Cast<UK2Node>(Node))
            {
                // Try to match by node name or GUID
                if (K2Node->GetName() == NodeIdentifier ||
                    K2Node->NodeGuid.ToString() == NodeIdentifier)
                {
                    return K2Node;
                }
            }
        }
    }
    
    return nullptr;
}

FBPVariableDescription* UPatchApplier::FindVariable(UBlueprint* Blueprint, const FString& VariableName)
{
    if (!Blueprint)
    {
        return nullptr;
    }
    
    for (FBPVariableDescription& Variable : Blueprint->NewVariables)
    {
        if (Variable.VarName.ToString() == VariableName)
        {
            return &Variable;
        }
    }
    
    return nullptr;
}

void UPatchApplier::BeginTransaction(const FString& Description)
{
    if (CurrentTransaction.IsValid())
    {
        EndTransaction();
    }
    
    CurrentTransaction = MakeUnique<FScopedTransaction>(FText::FromString(Description));
}

void UPatchApplier::EndTransaction()
{
    if (CurrentTransaction.IsValid())
    {
        CurrentTransaction.Reset();
    }
}

void UPatchApplier::CancelTransaction()
{
    if (CurrentTransaction.IsValid())
    {
        CurrentTransaction->Cancel();
        CurrentTransaction.Reset();
    }
}

void UPatchApplier::SetLastError(const FString& ErrorMessage)
{
    LastErrorMessage = ErrorMessage;
    UE_LOG(LogTemp, Error, TEXT("PatchApplier Error: %s"), *ErrorMessage);
}

void UPatchApplier::LogPatchOperation(TSharedPtr<FJsonObject> Operation, bool Success)
{
    if (!Operation.IsValid())
    {
        return;
    }
    
    FString OperationType;
    Operation->TryGetStringField(TEXT("type"), OperationType);
    
    FString BlueprintPath;
    Operation->TryGetStringField(TEXT("blueprint"), BlueprintPath);
    
    if (Success)
    {
        UE_LOG(LogTemp, Log, TEXT("Successfully applied patch operation '%s' to blueprint '%s'"), 
               *OperationType, *BlueprintPath);
    }
    else
    {
        UE_LOG(LogTemp, Warning, TEXT("Failed to apply patch operation '%s' to blueprint '%s'"), 
               *OperationType, *BlueprintPath);
    }
}